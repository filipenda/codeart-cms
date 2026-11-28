# Rich-text editor

Developer documentation (English). Decisions and reasons: `docs/DECISIONS.md` E1-E6.

`<x-codeart::form.editor>` is a TipTap (MIT, 3.35.1) editor wrapped in an Alpine component (`codeartEditor`, `resources/js/editor.js`), built into `resources/dist/codeart.js` with the local esbuild pipeline (`npm run build`, no CDN).

## Use it
```blade
<x-codeart::form.editor name="translations[sr][body]" id="f-sr-body" label="Tekst" :value="$value" :bag="$bag" />
```
- In a publishable module: give the field the type `editor` in `formFields()` (`'body' => 'editor'`); `publishing/form.blade.php` renders it once per language tab. Or generate it: `php artisan make:module blog-posts --translatable=title,body --editor=body`.
- Submits plain HTML in the textarea named `name`. Without JS the textarea is the whole field. Errors come from the `bag` like every other field; each language gets its own independent editor.
- The component includes the media picker (`<x-codeart::media.picker />`) once per page; the image button opens it through the window events `codeart:media-open` / `codeart:media-selected`.

## Toolbar
Undo, redo, paragraph / H2-H4, bold, italic, underline, strikethrough, code, bulleted and numbered lists, quote, horizontal line, link (dialog: address, new tab, nofollow; Ctrl+K), image from the media library, table (row/column tools appear inside a table), clear formatting, and **HTML source** (only for `manage-modules`, i.e. super-admin). Every control has an accessible label (`resources/lang/*/editor.php`); the usual shortcuts work (Ctrl+B/I/U, Ctrl+Z, Ctrl+Shift+Z, Ctrl+K).

## Server-side sanitizing (the security boundary)
`Codeart\Core\Editor\HtmlSanitizer`:
```php
HtmlSanitizer::clean($html);        // safe HTML (idempotent)
HtmlSanitizer::mediaIds($html);     // ids of media-library files the content uses (after the clean-up)
HtmlSanitizer::cleanTranslations($translations, ['body']);   // what PublishableController does for `editor` fields
```
- Allowed elements: `p br h2-h4 strong b em i u s del ul ol li blockquote a img table thead tbody tfoot tr th td colgroup col caption hr code pre figure figcaption span`.
- Allowed attributes: `a[href,title,target,rel]`, `img[src,srcset,sizes,alt,title,width,height,loading,data-media-id]`, `td/th[colspan,rowspan]`, `ol[start]`, and `style="text-align: left|center|right|justify"` on `p h2-h4 td th`. Nothing else (no classes, ids, event handlers).
- URLs: `http`, `https`, `mailto`, `tel` (images: `http`/`https` only) and relative paths. `javascript:`, `data:`, `vbscript:` and obfuscated variants (entities, tabs, control and invisible characters) are refused; a link without a safe address becomes its text.
- `target="_blank"` always adds `rel="noopener noreferrer"`.
- Images with `data-media-id` are rewritten from the media library (`src`, `srcset`, `width`, `height`), so content keeps working when files are renamed or moved. An unknown id is dropped; the image survives only with a safe `src`.
- Dropped with their content: script, style, iframe, object, embed, svg, math, template, noscript, forms and form controls, audio/video, base/meta/link. Unknown tags are unwrapped (text stays). Comments and processing instructions are removed.
- Any module that stores or prints user-provided HTML must pass it through this class on the server. Rendering on the public site (`{!! $content->body !!}`) is safe only because it was sanitized on save.
- The Cyrillic version is generated from the sanitized `sr` HTML; the transliterator leaves tags and attributes alone.

## Editor stylesheet (`codeart.editor_css`)
```php
'editor_css' => ['/css/site.css', 'https://cdn.example.com/site.css'],
```
The content area is a shadow root: the listed stylesheets are loaded inside it (so the content looks like the public site) and cannot leak into the admin; the admin's CSS cannot reach the content. Only absolute `http(s)` and root-relative URLs are accepted. The default content styles use tokens only and follow light/dark.
Limits: selectors for `html`/`body` do not match in a shadow root (use class selectors or `:host`); `@font-face` rules inside a shadow-root stylesheet are ignored by browsers, so the site's fonts must also be loaded by the admin page if they are needed.

## Unsaved changes
Forms with `data-dirty-guard` (the publishing form) ask before the page is left with changes; the editors write into their textarea on every change, so they are covered.

## Tests
`tests/Feature/EditorSanitizerTest.php` (XSS payload suite, idempotence, text-align, links, images, transliteration), `tests/Auth/EditorComponentTest.php` (component, labels per language, errors, stylesheet filter, built asset), `tests/Feature/Generator/EditorGeneratorTest.php` (generated module with `--editor` passes Pint, Larastan and its own tests).
