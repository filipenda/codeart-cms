<?php

namespace Codeart\Core\Publishing\Actions;

use Codeart\Core\Publishing\Revision;
use Illuminate\Database\Eloquent\Model;

/** Keeps the newest `codeart.publishing.max_revisions` revisions of a model (the current draft is never removed). */
final class PruneRevisions
{
    public function __invoke(Model $model): int
    {
        $max = max(1, (int) config('codeart.publishing.max_revisions', 20));
        $keep = Revision::query()->where('revisionable_type', $model->getMorphClass())->where('revisionable_id', $model->getKey())
            ->orderByDesc('id')->limit($max)->pluck('id')->all();
        $draft = $model->getAttribute('draft_revision_id');

        return Revision::query()->where('revisionable_type', $model->getMorphClass())->where('revisionable_id', $model->getKey())
            ->whereNotIn('id', $keep)->when($draft !== null, fn ($q) => $q->whereKeyNot($draft))->delete();
    }
}
