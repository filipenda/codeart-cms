<?php

namespace Codeart\Core\Media\Http;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Nested folders. Creating/renaming/moving needs `media.update`; deleting needs `media.delete` and the confirmation token. */
final class MediaFolderController
{
    public function store(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.update');
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', 'exists:media_folders,id']]);
        $folder = MediaFolder::query()->create(['name' => trim($data['name']), 'parent_id' => $data['parent_id'] ?? null]);

        return response()->json(['folders' => MediaFolder::tree(), 'id' => $folder->id], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize($request, 'media.update');
        $folder = MediaFolder::query()->findOrFail($id);
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'parent_id' => ['nullable', 'integer', 'exists:media_folders,id']]);
        if (array_key_exists('parent_id', $data) && $folder->isSelfOrAncestorOf($data['parent_id'] === null ? null : (int) $data['parent_id'])) {
            return response()->json(['message' => __('codeart::media.folder.cycle')], 422);
        }
        $folder->forceFill(array_filter(['name' => isset($data['name']) ? trim($data['name']) : null], fn ($v) => $v !== null))
            ->forceFill(array_key_exists('parent_id', $data) ? ['parent_id' => $data['parent_id']] : [])->save();

        return response()->json(['folders' => MediaFolder::tree()]);
    }

    /** Files and sub folders move up to the parent: nothing is deleted with the folder. */
    public function destroy(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.delete');
        $data = $request->validate(['id' => ['required', 'integer', 'exists:media_folders,id']]);
        $folder = MediaFolder::query()->findOrFail($data['id']);
        DB::transaction(function () use ($folder): void {
            Media::query()->where('folder_id', $folder->id)->update(['folder_id' => $folder->parent_id]);
            MediaFolder::query()->where('parent_id', $folder->id)->update(['parent_id' => $folder->parent_id]);
            $folder->delete();
        });

        return response()->json(['folders' => MediaFolder::tree()]);
    }

    private function authorize(Request $request, string $ability): void
    {
        abort_unless(Gate::forUser($request->user(Admin::guardName()) ?? abort(401))->allows($ability), 403);
    }
}
