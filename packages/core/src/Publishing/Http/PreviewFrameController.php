<?php

namespace Codeart\Core\Publishing\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Publishing\Preview;
use Codeart\Core\Publishing\PreviewManager;
use Codeart\Core\Publishing\PreviewTokens;
use Codeart\Core\Publishing\PublishableModel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/** `/<admin>/preview/{token}`: the real public page in a frame with desktop / tablet / mobile widths. */
final class PreviewFrameController
{
    public function show(Request $request, string $token, PreviewTokens $tokens, PreviewManager $preview): View
    {
        $user = $request->user(Admin::guardName()) ?? abort(401);
        $row = $tokens->find($token);
        abort_if($row === null || (int) $row->user_id !== (int) $user->getAuthIdentifier(), 404);

        $class = Model::getActualClassNameForMorph($row->model_type);
        abort_unless(class_exists($class) && is_subclass_of($class, PublishableModel::class), 404);
        /** @var class-string<Model&PublishableModel> $class */
        /** @var Model&PublishableModel $model */
        $model = $class::query()->findOrFail($row->model_id);
        abort_unless($user->can($model->permissionPrefix().'.update'), 403);
        $base = $model->publicUrl($row->locale) ?? abort(404);

        return view('codeart::admin.preview', [
            'url' => $preview->link($base, $token),
            'back' => Route::has($model->permissionPrefix().'.edit') ? route($model->permissionPrefix().'.edit', $model->getKey()) : route('admin.dashboard'),
            'parameter' => Preview::parameter(),
            'expires' => $row->expires_at,
        ]);
    }
}
