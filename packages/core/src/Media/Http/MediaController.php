<?php

namespace Codeart\Core\Media\Http;

use Codeart\Core\ActivityLog\ActivityLog;
use Codeart\Core\Auth\Admin;
use Codeart\Core\Confirmation\ConfirmationTokens;
use Codeart\Core\Media\Actions\DeleteMedia;
use Codeart\Core\Media\Actions\ReplaceMedia;
use Codeart\Core\Media\Actions\StoreMedia;
use Codeart\Core\Media\DuplicateMedia;
use Codeart\Core\Media\Media;
use Codeart\Core\Media\MediaFolder;
use Codeart\Core\Media\MediaInUse;
use Codeart\Core\Media\MediaQuery;
use Codeart\Core\Media\MediaRejected;
use Codeart\Core\Media\MediaTypes;
use Codeart\Core\Media\MediaUsage;
use Codeart\Core\Media\UploadLimit;
use Codeart\Core\Settings\InvalidSettingValue;
use Codeart\Core\Settings\Settings;
use Codeart\Core\Settings\SettingsManager;
use Codeart\Core\Translations\Locales;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Thin controller of the media library: permission, validation, then an Action. The page is Blade + Alpine, the data is JSON. */
final class MediaController
{
    public function index(Request $request): View
    {
        $this->authorize($request, 'media.view');
        $tokens = app(ConfirmationTokens::class);
        $confirm = function (string $target, string $title, string $text, string $button) use ($tokens): array {
            return ['target' => $target, 'verb' => 'POST', 'title' => $title, 'text' => $text, 'confirm' => $button, 'sig' => $tokens->sign($target, 'POST', $title, $text, $button)];
        };
        $limit = app(UploadLimit::class);

        return view('codeart::admin.media.index', [
            'folders' => MediaFolder::tree(),
            'limit' => $limit->describe(),
            'limitBytes' => $limit->effectiveBytes(),
            'allowed' => app(MediaTypes::class)->allowed(),
            'allTypes' => array_keys(MediaTypes::TYPES),
            'maxKb' => (int) app(Settings::class)->get('media.max_kb'),
            'can' => [
                'upload' => Gate::allows('media.upload'),
                'update' => Gate::allows('media.update'),
                'delete' => Gate::allows('media.delete'),
            ],
            'locales' => collect($this->manualLocales())->mapWithKeys(fn (string $l): array => [$l => app(Locales::class)->name($l)])->all(),
            'confirmDelete' => $confirm(route('admin.media.bulk-delete'), (string) __('codeart::media.delete.title'), (string) __('codeart::media.delete.text'), (string) __('codeart::media.delete.button')),
            'confirmForce' => $confirm(route('admin.media.bulk-delete'), (string) __('codeart::media.delete.force_title'), (string) __('codeart::media.delete.force_text'), (string) __('codeart::media.delete.force_button')),
            'confirmFolder' => $confirm(route('admin.media.folders.destroy'), (string) __('codeart::media.folder.delete_title'), (string) __('codeart::media.folder.delete_text'), (string) __('codeart::media.delete.button')),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.view');
        $user = $this->user($request);
        $page = MediaQuery::build($request, (int) $user->getAuthIdentifier())
            ->cursorPaginate(max(1, min(100, (int) $request->query('per_page', (int) config('codeart.media.per_page', 48)))));
        $locale = app(Locales::class)->current();

        return response()->json([
            'items' => $page->getCollection()->map(fn (Media $m): array => $m->toPicker($locale))->values(),
            'next' => $page->nextCursor()?->encode(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->authorize($request, 'media.view');
        $media = Media::query()->with('translations')->findOrFail($id);
        $translations = [];
        foreach ($this->manualLocales() as $locale) {
            $translations[$locale] = ['alt' => (string) ($media->translated('alt', $locale, false) ?? ''), 'title' => (string) ($media->translated('title', $locale, false) ?? '')];
        }

        return response()->json([
            'media' => $media->toPicker() + ['uploaded_by' => $media->uploaded_by, 'hash' => $media->hash],
            'translations' => $translations,
            'places' => MediaUsage::places($media),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.upload');
        $data = $request->validate([
            'file' => ['required', 'file'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'duplicate' => ['nullable', 'in:ask,reuse,keep'],
        ]);

        try {
            $media = app(StoreMedia::class)($request->file('file'), $this->user($request), isset($data['folder_id']) ? (int) $data['folder_id'] : null, (string) ($data['duplicate'] ?? 'ask'));
        } catch (DuplicateMedia $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => 'duplicate', 'duplicate' => $e->existing->toPicker()], 409);
        } catch (MediaRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
        }

        return response()->json(['media' => $media->toPicker()], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorize($request, 'media.update');
        $media = Media::query()->findOrFail($id);
        $data = $request->validate([
            'translations' => ['nullable', 'array'],
            'translations.*.alt' => ['nullable', 'string', 'max:255'],
            'translations.*.title' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);

        foreach ($this->manualLocales() as $locale) {
            if (isset($data['translations'][$locale])) {
                $media->setTranslation($locale, ['alt' => (string) ($data['translations'][$locale]['alt'] ?? ''), 'title' => (string) ($data['translations'][$locale]['title'] ?? '')]);
            }
        }
        if ($request->has('folder_id')) {
            $media->forceFill(['folder_id' => $data['folder_id'] ?? null])->save();
        }
        app(ActivityLog::class)->record('media.updated', 'File updated', $media, null, ['name' => $media->original_name], 'content');

        return response()->json(['media' => $media->fresh('translations')->toPicker()]);
    }

    public function replace(Request $request, int $id): JsonResponse
    {
        $this->authorize($request, 'media.update');
        $this->authorize($request, 'media.upload');
        $request->validate(['file' => ['required', 'file']]);
        $media = Media::query()->findOrFail($id);

        try {
            app(ReplaceMedia::class)($media, $request->file('file'), $this->user($request));
        } catch (MediaRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'reason' => $e->reason], 422);
        }

        return response()->json(['media' => $media->fresh('translations')->toPicker()]);
    }

    public function bulkMove(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.update');
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ]);
        $moved = Media::query()->whereIn('id', $data['ids'])->update(['folder_id' => $data['folder_id'] ?? null, 'updated_at' => now()]);
        app(ActivityLog::class)->record('media.moved', 'Files moved', null, null, ['count' => $moved, 'folder_id' => $data['folder_id'] ?? null], 'content');

        return response()->json(['moved' => $moved]);
    }

    /** Needs the confirmation token (route middleware). Files that are in use are listed and kept, unless `force` is sent (a second confirmation). */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $this->authorize($request, 'media.delete');
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer'], 'force' => ['nullable', 'boolean']]);
        $force = (bool) ($data['force'] ?? false);
        $deleted = [];
        $blocked = [];

        foreach (Media::query()->whereIn('id', $data['ids'])->get() as $media) {
            try {
                app(DeleteMedia::class)($media, $this->user($request), $force);
                $deleted[] = $media->id;
            } catch (MediaInUse $e) {
                $blocked[] = ['id' => $media->id, 'name' => $media->original_name, 'places' => $e->places];
            }
        }

        return response()->json(['deleted' => $deleted, 'blocked' => $blocked], $blocked !== [] && $deleted === [] ? 409 : 200);
    }

    /** Library limits (media.update): size and allowed types, written through the checked settings path. */
    public function settings(Request $request): RedirectResponse
    {
        $this->authorize($request, 'media.update');
        $data = $request->validate([
            'max_kb' => ['required', 'integer', 'min:1', 'max:2097152'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:'.implode(',', array_keys(MediaTypes::TYPES))],
        ]);
        try {
            $manager = app(SettingsManager::class);
            $manager->update($this->user($request), 'media.max_kb', (int) $data['max_kb']);
            $manager->update($this->user($request), 'media.allowed_types', array_values((array) ($data['types'] ?? [])));
        } catch (InvalidSettingValue|AuthorizationException $e) {
            return back()->with('toast', ['type' => 'danger', 'message' => $e->getMessage()]);
        }

        return back()->with('toast', ['type' => 'success', 'message' => __('codeart::media.settings.saved')]);
    }

    /** @return list<string> enabled languages written by hand (derived ones follow) */
    private function manualLocales(): array
    {
        $locales = app(Locales::class);

        return array_values(array_filter($locales->enabled(), fn (string $l): bool => $locales->derivedFrom($l) === null));
    }

    private function authorize(Request $request, string $ability): void
    {
        abort_unless(Gate::forUser($this->user($request))->allows($ability), 403);
    }

    private function user(Request $request): Authenticatable
    {
        return $request->user(Admin::guardName()) ?? abort(401);
    }
}
