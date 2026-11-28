<?php

namespace Codeart\Core\Media;

use Codeart\Core\Confirmation\ConfirmationTokens;
use Illuminate\Support\Facades\Gate;

/** What the Alpine media browser needs from the server: URLs, limits, permissions, texts. Shared by the library page and the picker dialog. */
final class BrowserConfig
{
    /** @return array<string, mixed> */
    public static function make(string $mode): array
    {
        $limit = app(UploadLimit::class);
        $tokens = app(ConfirmationTokens::class);
        $confirm = function (string $target, string $title, string $text, string $button) use ($tokens): array {
            return ['target' => $target, 'verb' => 'POST', 'title' => $title, 'text' => $text, 'confirm' => $button, 'sig' => $tokens->sign($target, 'POST', $title, $text, $button)];
        };
        $t = (array) __('codeart::media.js');

        return [
            'mode' => $mode,
            'multiple' => false,
            'type' => '',
            'folders' => MediaFolder::tree(),
            'itemsUrl' => route('admin.media.items'),
            'storeUrl' => route('admin.media.store'),
            'showUrl' => route('admin.media.show', ['id' => '__ID__']),
            'updateUrl' => route('admin.media.update', ['id' => '__ID__']),
            'replaceUrl' => route('admin.media.replace', ['id' => '__ID__']),
            'moveUrl' => route('admin.media.bulk-move'),
            'deleteUrl' => route('admin.media.bulk-delete'),
            'folderUrl' => route('admin.media.folders.store'),
            'folderUpdateUrl' => route('admin.media.folders.update', ['id' => '__ID__']),
            'folderDeleteUrl' => route('admin.media.folders.destroy'),
            'confirmUrl' => route('codeart.confirm'),
            'limitBytes' => $limit->effectiveBytes(),
            'limitHuman' => UploadLimit::human($limit->effectiveBytes()),
            'can' => ['upload' => Gate::allows('media.upload'), 'update' => Gate::allows('media.update'), 'delete' => Gate::allows('media.delete')],
            'confirm' => $mode === 'page' ? [
                'delete' => $confirm(route('admin.media.bulk-delete'), (string) __('codeart::media.delete.title'), (string) __('codeart::media.delete.text'), (string) __('codeart::media.delete.button')),
                'force' => $confirm(route('admin.media.bulk-delete'), (string) __('codeart::media.delete.force_title'), (string) __('codeart::media.delete.force_text'), (string) __('codeart::media.delete.force_button')),
                'folder' => $confirm(route('admin.media.folders.destroy'), (string) __('codeart::media.folder.delete_title'), (string) __('codeart::media.folder.delete_text'), (string) __('codeart::media.delete.button')),
            ] : null,
            't' => $t,
        ];
    }
}
