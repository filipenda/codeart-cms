<?php

namespace Codeart\Core\Publishing\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Publishing\Revision;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/** Makes an older revision the current draft (a new revision). The live content changes only when it is published. */
final class RestoreRevision
{
    public function __construct(private readonly SaveDraft $save, private readonly ActivityLog $log) {}

    public function __invoke(Model $model, Revision $revision, ?Authenticatable $user = null): Revision
    {
        if ($revision->revisionable_type !== $model->getMorphClass() || (int) $revision->revisionable_id !== (int) $model->getKey()) {
            throw new InvalidArgumentException('The revision belongs to another record.');
        }
        $restored = ($this->save)($model, $revision->content, $user, 'restored');
        $this->log->record('publishing.restored', 'Revision restored', $model, $user instanceof Model ? $user : null, ['revision' => $revision->id], 'content');

        return $restored;
    }
}
