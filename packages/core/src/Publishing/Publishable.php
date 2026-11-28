<?php

namespace Codeart\Core\Publishing;

use Codeart\Core\Translations\Locales;
use Codeart\Core\Translations\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;

/**
 * Draft / published / scheduled workflow for a translatable model (see docs/publishing-and-preview.md).
 *
 * The model's own tables hold the LIVE content (what the public site shows). Drafts live in `codeart_revisions`
 * and only reach the live tables when somebody publishes. Table columns the model needs:
 * `status` (string 20, default draft), `published_at`, `scheduled_at` (nullable timestamps), `draft_revision_id` (nullable bigint).
 *
 * The model implements: `translatedAttributes()` (Translatable), `permissionPrefix()` and `formFields()`.
 *
 * @mixin Model
 *
 * @phpstan-require-implements PublishableModel
 */
trait Publishable
{
    use Translatable;

    /** Permission prefix (`pages` for `pages.update`, `pages.publish` ...); also the config key of `public_url`. */
    abstract public function permissionPrefix(): string;

    /**
     * Fields of the edit form: attribute => `text` | `textarea` | `editor` (rich text, sanitized on save). The order is the order on the page; the label is
     * the translation `<module>::module.fields.<attribute>` when it exists.
     *
     * @return array<string, string>
     */
    abstract public function formFields(): array;

    /** Override with `false` for a module without the draft step: saving publishes immediately, no status, preview or history. */
    public function usesWorkflow(): bool
    {
        return true;
    }

    /** Columns of the model table (not translated) that are drafted and published with the content. */
    public function publishableColumns(): array
    {
        return [];
    }

    /**
     * Media fields of the form (stored as a JSON list of media ids in a column of the model table; list the column in
     * `publishableColumns()` so it is drafted and published with the text): `['cover' => ['multiple' => false], 'gallery' => ['multiple' => true]]`.
     * On publish the usage is recorded, so the library knows where a file is used.
     *
     * @return array<string, array{multiple: bool}>
     */
    public function mediaFields(): array
    {
        return [];
    }

    public function initializePublishable(): void
    {
        $this->mergeCasts([
            'status' => PublishStatus::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ]);
    }

    /** @return MorphMany<Revision, Model> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable')->orderByDesc('id');
    }

    /** @return BelongsTo<Revision, Model> */
    public function draftRevision(): BelongsTo
    {
        return $this->belongsTo(Revision::class, 'draft_revision_id');
    }

    /**
     * What the public site may show: published, and not before its publication time.
     *
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), PublishStatus::Published->value)
            ->where(fn (Builder $q) => $q->whereNull($this->qualifyColumn('published_at'))->orWhere($this->qualifyColumn('published_at'), '<=', now()));
    }

    /**
     * Published content, plus the one model a valid preview link of this request points to (draft or not).
     *
     * @param  Builder<static>  $query
     */
    public function scopePreviewable(Builder $query, Request $request): void
    {
        $id = app(PreviewManager::class)->modelId($request, static::class);
        if ($id === null) {
            $query->published();

            return;
        }

        $query->where(fn (Builder $q) => $q->published()->orWhere($this->qualifyColumn($this->getKeyName()), $id));
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }

    public function hasDraft(): bool
    {
        return $this->getAttribute('draft_revision_id') !== null;
    }

    /**
     * The live content as a revision payload.
     *
     * @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>}
     */
    public function liveContent(): array
    {
        $locales = app(Locales::class);
        $translations = [];
        foreach ($this->translations()->get() as $row) {
            if ($locales->derivedFrom($row->locale) !== null) {
                continue; // generated from the source locale when publishing
            }
            $translations[$row->locale] = collect($this->translatedAttributes())->mapWithKeys(fn (string $a): array => [$a => $row->getAttribute($a)])->all();
        }

        return ['fields' => collect($this->publishableColumns())->mapWithKeys(fn (string $c): array => [$c => $this->getAttribute($c)])->all(), 'translations' => $translations];
    }

    /**
     * What the edit form starts from: the draft when there is one, else the live content.
     *
     * @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>}
     */
    public function currentContent(): array
    {
        $draft = $this->hasDraft() ? $this->draftRevision : null;

        return $draft !== null
            ? ['fields' => $draft->fields(), 'translations' => $draft->translations()]
            : $this->liveContent();
    }

    /** First text field in the content: the name of the row in lists (draft text when nothing is published yet). */
    public function displayTitle(): string
    {
        $first = (string) array_key_first($this->formFields());
        $live = $this->translated($first);
        if (is_string($live) && $live !== '') {
            return $live;
        }
        $default = app(Locales::class)->default();

        return (string) ($this->currentContent()['translations'][$default][$first] ?? '');
    }

    /**
     * Address of the real public page in a locale, from the `<prefix>.public_url` config pattern
     * (`{slug}`, `{id}`, `{locale}`). Null when no pattern is configured.
     */
    public function publicUrl(?string $locale = null): ?string
    {
        $pattern = config($this->permissionPrefix().'.public_url');
        if (! is_string($pattern) || $pattern === '') {
            return null;
        }
        $locale ??= app(Locales::class)->default();
        $slug = in_array('slug', $this->translatedAttributes(), true) ? ($this->translated('slug', $locale) ?? '') : '';
        $url = strtr($pattern, ['{id}' => (string) $this->getKey(), '{slug}' => (string) $slug, '{locale}' => $locale]);

        return preg_match('#^https?://#', $url) === 1 ? $url : url($url);
    }
}
