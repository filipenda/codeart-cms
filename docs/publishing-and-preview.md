# Publishing workflow and real-site preview

Developer documentation (English). The owner's manual is written at the end of the project. Decisions and reasons: `docs/DECISIONS.md` (P1-P6, V1-V4).

A module generated with `php artisan make:module` has all of this. To add it to a model by hand: implement `Codeart\Core\Publishing\PublishableModel`, `use Publishable`, give the table the columns `status` (string 20, default `draft`), `published_at`, `scheduled_at` (nullable timestamps) and `draft_revision_id` (nullable bigint), register the class once with `app(Publishables::class)->register(Page::class)` in the provider, and declare `<module>.view|create|update|publish|delete`.

## Model
| | |
|---|---|
| Live content | the model's own tables (`pages_pages`, `pages_page_translations`): what the public site shows |
| Draft and history | `codeart_revisions` (JSON per language), the model's `draft_revision_id` points to the current draft |
| Statuses | `draft`, `published`, `scheduled` (`Codeart\Core\Publishing\PublishStatus`) |
| Public query | `Page::published()`: status `published` and `published_at <= now` |

**Saving a draft never changes the live content.** "Objavi" (publish) copies the draft into the live translations, sets `published`, clears the draft pointer and marks the revision `published`. Languages that are generated (`sr-Cyrl`) are produced from `sr` at that moment; a language without any text is not written.

Actions (one class each, `Codeart\Core\Publishing\Actions`): `SaveDraft`, `PublishContent($model, $user, ?$at)` (a future `$at` only schedules), `UnpublishContent`, `RestoreRevision`, `PublishDue`, `PruneRevisions`. Controllers, buttons and the scheduler all call these.

History keeps the newest `codeart.publishing.max_revisions` (default 20) revisions per record. "Restore" turns an old revision into the current draft (a new revision); it does not publish.

## Scheduling
`publishing:run` runs every minute from the Laravel scheduler (the same `* * * * * php artisan schedule:run` cron line as everything else; no queue worker). It publishes scheduled records whose time has come and deletes expired preview links. A record is public only after that run, so a schedule can be up to a minute late, never early.

## Permissions
`<module>.publish` is separate from `<module>.update`. Default grants of a generated module: `admin` all, `editor` view/create/update. Changing the status (form, row action, bulk action) needs `publish`; the server refuses it otherwise. Activity log `content`: `publishing.published|unpublished|scheduled|restored|deleted` with user and record.

## Reading content on the client site
The CMS never dictates how the public site looks. A client route loads the record and asks `Preview` for its content:
```php
use Codeart\Core\Publishing\Preview;
use Codeart\Pages\Models\Page;

Route::get('/pages/{slug}', function (Request $request, string $slug) {
    // The record the preview link points to (draft or not), otherwise a published one.
    $page = Preview::model($request, Page::class)
        ?? Page::published()->whereTranslatedSlug($slug)->firstOrFail();

    return view('site.page', ['content' => Preview::resolve($request, $page)]);
});
```
```blade
{{-- resources/views/site/page.blade.php --}}
<article>
    <h1>{{ $content->title }}</h1>
    {!! $content->body !!}   {{-- the client decides how rich text is rendered and sanitised --}}
</article>
@if ($content->isPreview) <p class="draft-banner">Draft preview</p> @endif
```
- `Preview::resolve($request, $model, ?$locale)` returns a `PreviewContent` (`->title`, `->get('title')`, `->isPreview`, `->toArray()`): the **draft** text when the request carries a valid preview link for this very record, otherwise the **published** text (locale fallback chain applies, `sr-Cyrl` follows `sr`).
- `Preview::model($request, Page::class)` returns the previewed record or null. Use it because a never-published draft has no slug yet. `Page::previewable($request)` is a scope that adds the previewed record to the published ones.
- The route must be in the `web` group (the preview needs the session of the signed-in editor). Nothing else is required: responses to requests that carry the preview parameter are `X-Robots-Tag: noindex, nofollow, noarchive`, `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer`, `Vary: Cookie`. Do not cache pages that depend on `Preview` in a CDN that ignores `Cache-Control`/`Vary`.
- Drafts never leak: without a valid link everything above returns published content only.

## Preview in the admin form
The form has "Save draft", "Preview", "Preview in a frame" and (with `publish`) a status control (draft / published / scheduled + time in the editor's time zone). **Preview** saves the form as a draft revision and opens the real page in a new tab: `config('<module>.public_url')` (`{slug}`, `{id}`, `{locale}`; the client overrides it) plus `?codeart_preview=<token>`. **Preview in a frame** opens `/<admin path>/preview/{token}` which shows that same URL in an iframe with Desktop (100%), Tablet (768px) and Mobile (375px) buttons. The buttons appear once the record has been saved.

### Token rules (`PreviewTokens`, `PreviewManager`)
- 48 random characters; only the SHA-256 is stored with model, revision, locale, user and expiry (`codeart.preview.ttl_minutes`, default 60). Not guessable, not derivable, revocable (`PreviewTokens::revoke`) and dead after expiry.
- Valid only when: it exists and has not expired; the request is signed in on the admin guard as the user it was issued to; the model implements `PublishableModel`; the user still has `<prefix>.update`; the revision still exists; the record is the one in the link. Anything else: published content, no hint why.
- An editor can only preview what that very revision contains; a later save makes a new link.

## Editor stylesheet
`config/codeart.php` has `editor_css` (a list of URLs of the client site's CSS). Only the key exists; the rich-text editor (TipTap) that will load it is a later task.

## Tests
- Core: `tests/Auth/Publishing` (workflow, revisions, scheduling with a faked clock, preview: valid, expired, tampered, wrong user, lost permission, headers, frame) and `tests/Feature/Generator` (generated modules pass Pint, Larastan and their own tests).
- A generated module's tests extend `Codeart\Core\Testing\ModuleTestCase`.
