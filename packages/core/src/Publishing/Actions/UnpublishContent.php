<?php

namespace Codeart\Core\Publishing\Actions;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Publishing\PublishStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/** Takes the model off the public site (also cancels a schedule). Content and drafts stay as they are. */
final class UnpublishContent
{
    public function __construct(private readonly ActivityLog $log) {}

    public function __invoke(Model $model, ?Authenticatable $user = null): Model
    {
        $model->forceFill(['status' => PublishStatus::Draft, 'scheduled_at' => null])->save();
        $this->log->record('publishing.unpublished', 'Content unpublished', $model, $user instanceof Model ? $user : null, [], 'content');

        return $model;
    }
}
