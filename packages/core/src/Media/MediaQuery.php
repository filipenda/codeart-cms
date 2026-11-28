<?php

namespace Codeart\Core\Media;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The one query behind the library page and the picker. Built for millions of rows: every filter hits an index, the page is
 * a cursor (no COUNT, no OFFSET), the translated texts of the page are loaded with ONE extra query.
 */
final class MediaQuery
{
    public const array SORTS = ['newest', 'oldest', 'name', 'size'];

    public const array TYPES = ['image', 'pdf', 'document', 'archive', 'other'];

    /** @return Builder<Media> */
    public static function build(Request $request, ?int $userId = null): Builder
    {
        $query = Media::query()->with('translations');

        $type = (string) $request->query('type', '');
        match ($type) {
            'image' => $query->where('mime', 'like', 'image/%'),
            'pdf' => $query->where('mime', 'application/pdf'),
            'archive' => $query->whereIn('mime', ['application/zip']),
            'document' => $query->where(fn (Builder $q) => $q->where('mime', 'like', 'application/vnd.%')->orWhereIn('mime', ['application/msword', 'text/plain', 'text/csv'])),
            'other' => $query->where('mime', 'not like', 'image/%')->whereNotIn('mime', ['application/pdf', 'application/zip']),
            default => null,
        };

        $folder = $request->query('folder');
        if ($folder === 'root') {
            $query->whereNull('folder_id');
        } elseif (is_numeric($folder)) {
            $query->where('folder_id', (int) $folder);
        }

        $from = self::date($request->query('from'));
        $to = self::date($request->query('to'));
        if ($from !== null) {
            $query->where('created_at', '>=', $from.' 00:00:00');
        }
        if ($to !== null) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $uploader = $request->query('uploader');
        if ($uploader === 'me' && $userId !== null) {
            $query->where('uploaded_by', $userId);
        } elseif (is_numeric($uploader)) {
            $query->where('uploaded_by', (int) $uploader);
        }

        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = '%'.addcslashes(mb_substr($term, 0, 100), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('original_name', 'like', $like)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('media_translations')
                    ->whereColumn('media_translations.media_id', 'media.id')
                    ->where(fn ($t) => $t->where('alt', 'like', $like)->orWhere('title', 'like', $like))));
        }

        match ((string) $request->query('sort', 'newest')) {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('original_name')->orderBy('id'),
            'size' => $query->orderByDesc('size')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return $query;
    }

    private static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
