<?php

namespace Codeart\Core\Publishing\Actions;

use Carbon\CarbonInterface;
use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Media\MediaUsage;
use Codeart\Core\Publishing\PublishableModel;
use Codeart\Core\Publishing\PublishStatus;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * "Objavi": copies the draft to the live content and makes the model public. With a future `$at` it only schedules:
 * the scheduler (`publishing:run`) does the copy when the time comes.
 */
final class PublishContent
{
    public function __construct(private readonly ActivityLog $log) {}

    public function __invoke(Model $model, ?Authenticatable $user = null, ?CarbonInterface $at = null): Model
    {
        /** @var Model&PublishableModel $model */
        if ($at !== null && $at->isFuture()) {
            $model->forceFill(['status' => PublishStatus::Scheduled, 'scheduled_at' => $at])->save();
            $this->log->record('publishing.scheduled', 'Content scheduled', $model, $user instanceof Model ? $user : null, ['at' => $at->toIso8601String()], 'content');

            return $model;
        }

        $wasPublished = $model->getAttribute('status') === PublishStatus::Published;
        DB::transaction(function () use ($model, $wasPublished): void {
            $draft = $model->hasDraft() ? $model->draftRevision()->first() : null;
            if ($draft !== null) {
                $model->forceFill($draft->fields());
                $model->save(); // the model row first: translations need it
                $this->applyTranslations($model, $draft->translations());
                $draft->forceFill(['kind' => 'published'])->save();
            }
            $model->forceFill([
                'status' => PublishStatus::Published,
                'published_at' => $wasPublished && $model->getAttribute('published_at') !== null ? $model->getAttribute('published_at') : now(),
                'scheduled_at' => null,
                'draft_revision_id' => null,
            ])->save();
        });
        $model->unsetRelation('draftRevision');
        MediaUsage::syncPublished($model);
        $this->log->record('publishing.published', 'Content published', $model, $user instanceof Model ? $user : null, [], 'content');

        return $model;
    }

    /** @param  array<string, array<string, mixed>>  $translations */
    private function applyTranslations(Model $model, array $translations): void
    {
        /** @var Model&PublishableModel $model */
        $locales = app(Locales::class);
        // The source locale first, so the derived locales are generated from what was just saved.
        uksort($translations, fn (string $a, string $b): int => ($b === $locales->default()) <=> ($a === $locales->default()));
        foreach ($translations as $locale => $values) {
            if (array_filter($values, fn (mixed $v): bool => $v !== null && $v !== '') === []) {
                continue; // nothing written in this language: leave the live translation alone
            }
            $model->setTranslation($locale, $values);
        }
    }
}
