<?php

namespace Codeart\Core\Users;

use Codeart\Core\Admin\Dashboard\WidgetData;
use Codeart\Core\Auth\Admin;
use Illuminate\Contracts\Auth\Authenticatable;

/** Real number only: registrations that wait for approval. */
final class PendingUsersWidget implements WidgetData
{
    public function data(?Authenticatable $user): array
    {
        return ['count' => Admin::userModel()::query()->whereNull('approved_at')->count()];
    }
}
