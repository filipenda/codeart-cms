<?php

namespace Codeart\Core\Publishing\Actions;

use Codeart\Core\Publishing\PublishableModel;
use Codeart\Core\Publishing\Revision;
use Codeart\Core\Translations\Locales;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores content as the model's current draft. The live tables are NOT touched, so the public site never changes.
 * Saving the same content again returns the existing draft revision (no history noise).
 */
final class SaveDraft
{
    public function __construct(private readonly PruneRevisions $prune) {}

    /**
     * @param  array{fields?: array<string, mixed>, translations?: array<string, array<string, mixed>>}  $content
     */
    public function __invoke(Model $model, array $content, ?Authenticatable $user = null, string $kind = 'draft'): Revision
    {
        $content = $this->normalise($model, $content);
        $current = $model->getAttribute('draft_revision_id') !== null ? Revision::query()->find($model->getAttribute('draft_revision_id')) : null;
        if ($current !== null && $current->content === $content) {
            return $current;
        }

        $revision = Revision::query()->create([
            'revisionable_type' => $model->getMorphClass(),
            'revisionable_id' => $model->getKey(),
            'kind' => $kind,
            'content' => $content,
            'user_id' => $user?->getAuthIdentifier(),
        ]);
        $model->forceFill(['draft_revision_id' => $revision->id])->save();
        ($this->prune)($model);

        return $revision;
    }

    /**
     * Only declared attributes, only manual locales (derived ones are generated when publishing), strings as strings.
     *
     * @param  array{fields?: array<string, mixed>, translations?: array<string, array<string, mixed>>}  $content
     * @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>}
     */
    private function normalise(Model $model, array $content): array
    {
        /** @var Model&PublishableModel $model */
        $locales = app(Locales::class);
        $translations = [];
        foreach ((array) ($content['translations'] ?? []) as $locale => $values) {
            if (! $locales->isEnabled($locale) || $locales->derivedFrom($locale) !== null) {
                continue;
            }
            $translations[$locale] = [];
            foreach ($model->translatedAttributes() as $attribute) {
                $translations[$locale][$attribute] = isset($values[$attribute]) && is_scalar($values[$attribute]) ? (string) $values[$attribute] : '';
            }
        }
        ksort($translations);

        $fields = [];
        foreach ($model->publishableColumns() as $column) {
            $fields[$column] = ((array) ($content['fields'] ?? []))[$column] ?? null;
        }

        return ['fields' => $fields, 'translations' => $translations];
    }
}
