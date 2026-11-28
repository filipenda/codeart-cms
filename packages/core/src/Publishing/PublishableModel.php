<?php

namespace Codeart\Core\Publishing;

use Codeart\Core\Translations\Translation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The contract of a model with the draft / published / scheduled workflow. Implement it and `use Publishable`
 * (the trait supplies everything except `permissionPrefix()` and `formFields()`). Static analysis and the preview
 * code type against this interface, because a trait cannot be a type.
 *
 * @property PublishStatus $status
 * @property Carbon|null $published_at
 * @property Carbon|null $scheduled_at
 * @property int|null $draft_revision_id
 */
interface PublishableModel
{
    public function permissionPrefix(): string;

    /** False = no draft step: saving the form publishes at once (a module generated with `--publishable=false`). */
    public function usesWorkflow(): bool;

    /** @return array<string, string> attribute => `text` | `textarea` | `editor` (rich text, sanitized on save) */
    public function formFields(): array;

    /** @return list<string> */
    public function publishableColumns(): array;

    /** @return list<string> */
    public function translatedAttributes(): array;

    /** @return MorphMany<Revision, Model> */
    public function revisions(): MorphMany;

    /** @return BelongsTo<Revision, Model> */
    public function draftRevision(): BelongsTo;

    /** @return class-string<Translation> */
    public function translationModel(): string;

    /** @return HasMany<Translation, Model> */
    public function translations(): HasMany;

    public function translated(string $attribute, ?string $locale = null, bool $withFallback = true): mixed;

    /** @param array<string, mixed> $values */
    public function setTranslation(string $locale, array $values): Translation;

    public function isPublished(): bool;

    public function hasDraft(): bool;

    /** @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>} */
    public function liveContent(): array;

    /** @return array{fields: array<string, mixed>, translations: array<string, array<string, mixed>>} */
    public function currentContent(): array;

    public function displayTitle(): string;

    public function publicUrl(?string $locale = null): ?string;
}
