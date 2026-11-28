# Media library

Developer documentation (English). Core, always on. Decisions: `docs/DECISIONS.md` (M1-M12). The owner's manual is written at the end of the project.

## What it is
Files live on a Laravel filesystem disk (`codeart.media.disk`, default `public`) under `uploads/YYYY/MM/<sanitized-name>-<random8>.<ext>`. The database (`media`, `media_folders`, `media_translations`, `media_usages`) holds the metadata. **Everything stores the media id, never a path**, so a file can be renamed, moved or replaced without breaking content.

| Table | Purpose |
|---|---|
| `media` | disk, path, original_name, mime, size, width, height, `hash` (SHA-256 of the uploaded bytes), `variants` (JSON), folder_id, uploaded_by, soft deletes. Indexed for folder, type, uploader, date, name, hash. |
| `media_folders` | nested folders (`parent_id`) |
| `media_translations` | `alt`, `title` per locale (core's `Translatable`; `sr-Cyrl` follows `sr`) |
| `media_usages` | polymorphic: media, record, field, position. Written by `HasMedia` and by publishing. |

Permissions (guard `admin`): `media.view`, `media.upload`, `media.update`, `media.delete`; `admin` has all, `super-admin` passes every check. `editor` has none by default (grant `media.view` + `media.upload` where editors pick images).

## Upload security
Decided by the **content** (`finfo`), never by the name or the browser's MIME (`MediaTypes::detect`):
- Allowlist = setting `media.allowed_types` (default jpg png webp gif pdf doc docx xls xlsx ppt pptx odt ods txt csv; `zip` can be switched on). SVG, HTML, scripts and executables are **not in the map**, so no setting can allow them.
- A name that contains a blocked extension anywhere (`shell.php.jpg`, `.htaccess`, `x.svg`) is refused (`MediaTypes::BLOCKED_EXTENSIONS`).
- Images must be real images (`ImageProcessor::probe`), at most `codeart.media.max_pixels` (decompression bombs). Office files must contain their marker entries (`word/document.xml` ...); a zip renamed to `.docx` is refused.
- JPEG/PNG/WebP are re-encoded by the ImageProcessor, which drops EXIF (GPS), XMP and comments and applies the camera rotation. **The stored original is this stripped copy** (the privacy rule wins over bit-exactness). GIF is stored as is.
- The stored name is built by us (ASCII slug + random suffix + the extension of the DETECTED type); the client name is only kept as `original_name`. Nothing can leave the disk root.
- Zero-byte files, files above the effective limit and corrupt images are refused with a plain message (`MediaRejected`, translated `codeart::media.reject.*`).
- Duplicates: same SHA-256 -> 409 `duplicate`; the client may `reuse` the existing file or `keep` both.

### Size limit
Effective limit = the smallest of `media.max_kb` (editable in the library page for `media.update`), PHP `upload_max_filesize` and `post_max_size` (`UploadLimit`). The page shows it in plain Serbian/English and, when PHP is the bottleneck, says which value the hosting provider has to raise (cPanel: MultiPHP INI Editor).

### Serving files safely
Files on the public disk must never execute. The library writes `uploads/.htaccess` (php engine off, handlers removed, `nosniff`) on the first upload (Apache/LiteSpeed). **Nginx** needs the equivalent in the server config:
```nginx
location ^~ /storage/uploads/ { location ~* \.(php\d?|phtml|phar)$ { return 403; } add_header X-Content-Type-Options nosniff; }
```
The library itself never stores a script type, so this is defence in depth.

## Variants
`MediaVariants` writes the sizes of `codeart.media.variants` (default `thumb` 320, `medium` 800, `large` 1600; never enlarged, `thumb` always) next to the original as `<name>-<size>.<ext>` plus a `.webp` copy when GD can encode WebP (`codeart.media.webp`). No on-the-fly route (shared hosting). A failure of one size is reported (`report()`) and skipped: the original stays usable and `variantUrl()` falls back to it. `php artisan media:regenerate [--id=1 --id=2] [--missing]` rebuilds them (chunked).
```php
$media->url();                 // original (root-relative for local disks)
$media->variantUrl('medium');  // falls back to the original
$media->variantUrl('medium', webp: true);
$media->srcset();              // "/storage/..-thumb.jpg 320w, ... 2400w"
$media->translated('alt');     // current language, fallback chain
$media->toPicker();            // the array the picker, the form field and the editor use
```

## Admin UI
Menu item "Medija biblioteka" (`admin.media.index`): grid and list view, search (name, alt, title), filters (type, date range, uploader, folder), sort, **cursor paging** (no COUNT, no OFFSET, one page query + one query for the translations of the page, bounded at any table size), multi-file drag and drop with per-file progress and errors, folder tree, detail drawer (preview, copy address, alt/title per language, replace file keeping the id, "used in"), bulk move, bulk delete. All data is JSON (`admin.media.items|show|update|replace|bulk-move|bulk-delete|folders.*`); the Alpine component is `codeartMedia` in `resources/js/media.js`.

Deleting is destructive: the route carries `codeart.confirmed`. The page asks the confirmation step (`codeart.confirm`, signed target) for a one-time token, then posts it. A file that is **in use** is not deleted: the answer is 409 with the list of places; "Delete anyway" asks for a second token and posts `force=1`, which clears the references. Deleting removes the files from the disk and soft-deletes the row. Activity log (`content`): `media.uploaded|replaced|updated|moved|deleted`.

## For modules
```php
use Codeart\Core\Media\HasMedia;          // trait: syncMedia($field, [ids]), mediaFor($field), mediaUsages()
$page->syncMedia('gallery', [12, 7, 30]); // order kept; unknown ids ignored
$page->mediaFor('gallery');               // Collection<Media> in that order (2 queries)
```
Blade: `<x-codeart::form.media name="cover" :label="__('...')" :value="$page->cover" />` (`multiple` for several, reorderable); it stores **ids** (`cover` or `gallery[]`) and includes the picker dialog once. The picker is also reachable from your own JS: dispatch `codeart:media-open` `{token, multiple, type}` on `window`, listen for `codeart:media-selected` `{token, items}`. `form.image` (logo, avatar) is separate and unchanged.

A **publishable** module declares its media fields (generator: `--media`):
```php
public function mediaFields(): array { return ['cover' => ['multiple' => false], 'photos' => ['multiple' => true]]; }
public function publishableColumns(): array { return ['cover', 'photos']; }   // JSON columns of ids, cast 'array'
```
The ids are drafted and published with the text (`PublishableController` validates them against the library); publishing records the usage (`MediaUsage::syncPublished`: media fields and `data-media-id` images in `editor` fields), the list gets a thumbnail column, and the library refuses to delete what published content uses.

## Generator
`php artisan make:module albums --translatable=title,body --media=cover,photos:many` adds the `HasMedia` trait, the JSON columns, the form fields, a thumbnail column in the list and `tests/MediaTest.php`. `name` = one file, `name:many` = several in a chosen order. Combine with `--editor=body`.

## Tests
`tests/Auth/Media`: upload matrix (spoofed MIME, svg, php, double extension, zero byte, oversize, corrupt, EXIF), variants/webp, duplicates, folders, move/delete with confirmation tokens, usage blocking and forced removal, permissions per role, bounded queries on a 6000-row table, translated alt/title, `media:regenerate`, views. `tests/Feature/Generator/MediaGeneratorTest.php` generates a module into a temp directory and runs Pint, Larastan and its tests.
