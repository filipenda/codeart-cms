<?php

namespace Codeart\Core\Translations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Translatable Eloquent model: one `<model>_translations` table, one row per locale.
 *
 * The model implements translatedAttributes() (include 'slug' if it has one) and has a sibling
 * `<Model>Translation extends Translation` class. Public API:
 *   setTranslation($locale, [...])      save one locale (derived-locale rules apply)
 *   getTranslation($locale, $fallback)  the translation row (fallback chain on by default)
 *   hasTranslation($locale)             exact locale only, no fallback
 *   translated('title', $locale)        one attribute; $model->title = current locale with fallback
 *   resetToAutomatic($locale, ...$attributes)  give a hand-edited derived value back to the transliterator
 *   whereTranslatedSlug($slug, $locale) query scope
 *   uniqueSlug($text, $locale)          free slug for a locale
 *
 * @mixin Model
 */
trait Translatable
{
    /** @return list<string> */
    abstract public function translatedAttributes(): array;

    /** @return class-string<Translation> */
    public function translationModel(): string
    {
        return static::class.'Translation';
    }

    /** Attribute the slug is generated from. */
    public function slugSource(): string
    {
        return 'title';
    }

    /** @return HasMany<covariant Translation, $this> */
    public function translations(): HasMany
    {
        return $this->hasMany($this->translationModel(), $this->getForeignKey());
    }

    public function getAttribute($key)
    {
        if (in_array($key, $this->translatedAttributes(), true) && ! array_key_exists($key, $this->attributes)) {
            return $this->translated($key);
        }

        return parent::getAttribute($key);
    }

    public function hasTranslation(string $locale): bool
    {
        return $this->translationIn($locale) !== null;
    }

    public function getTranslation(?string $locale = null, bool $withFallback = true): ?Translation
    {
        $locales = app(Locales::class);
        $locale ??= $locales->current();

        foreach ($withFallback ? $locales->chain($locale) : [$locale] as $candidate) {
            if ($translation = $this->translationIn($candidate)) {
                return $translation;
            }
        }

        return null;
    }

    public function translated(string $attribute, ?string $locale = null, bool $withFallback = true): mixed
    {
        return $this->getTranslation($locale, $withFallback)?->getAttribute($attribute);
    }

    /**
     * Save the translation for one locale; returns the saved row. Only the given attributes change.
     *
     * - Manual locale (sr, en): saved as given; a missing slug is generated (unique per locale).
     *   Saving a source locale regenerates its derived locales for every attribute not hand-edited.
     * - Derived locale (sr-Cyrl): the slug always comes from the source. A value that differs from
     *   the transliterated source is marked as hand-edited (manual_fields); one that equals it is not.
     *
     * @param  array<string, mixed>  $values
     */
    public function setTranslation(string $locale, array $values): Translation
    {
        $locales = app(Locales::class);
        $locales->assertEnabled($locale);

        if (! $this->exists) {
            throw new LogicException('Save the model before setting translations.');
        }

        $values = Arr::only($values, $this->translatedAttributes());

        return DB::transaction(function () use ($locales, $locale, $values): Translation {
            $source = $locales->derivedFrom($locale);
            $saved = $source === null
                ? $this->saveManualLocale($locale, $values)
                : $this->saveDerivedLocale($locale, $source, $values);

            if ($source === null) {
                foreach ($locales->derivedLocales($locale) as $derived) {
                    $this->regenerate($derived);
                }
            }

            $this->unsetRelation('translations');

            return $saved;
        });
    }

    /** Remove attributes (default: all) from manual_fields and regenerate them from the source. */
    public function resetToAutomatic(string $locale, string ...$attributes): ?Translation
    {
        $translation = $this->freshTranslation($locale);
        if ($translation === null || app(Locales::class)->derivedFrom($locale) === null) {
            return $translation;
        }

        $manual = $attributes === [] ? [] : array_values(array_diff($translation->manual_fields ?? [], $attributes));
        $translation->manual_fields = $manual;
        $translation->save();

        $result = $this->regenerate($locale);
        $this->unsetRelation('translations');

        return $result;
    }

    /** @param  Builder<static>  $query */
    public function scopeWhereTranslatedSlug(Builder $query, string $slug, ?string $locale = null): void
    {
        $locale ??= app(Locales::class)->current();

        $query->whereHas('translations', fn ($q) => $q->where('locale', $locale)->where('slug', $slug));
    }

    /** A slug for $text that is free in $locale. $ignoreId = the translation row being updated. */
    public static function uniqueSlug(string $text, string $locale, ?int $ignoreId = null): string
    {
        $class = (new static)->translationModel();
        $base = Slug::make($text);
        $slug = $base;

        for ($n = 2; $class::query()->where('locale', $locale)->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function translationIn(string $locale): ?Translation
    {
        /** @var Collection<int, Translation> $all */
        $all = $this->translations;

        return $all->firstWhere('locale', $locale);
    }

    private function freshTranslation(string $locale): ?Translation
    {
        return $this->translations()->where('locale', $locale)->first();
    }

    /** @param  array<string, mixed>  $values */
    private function saveManualLocale(string $locale, array $values): Translation
    {
        $translation = $this->freshTranslation($locale) ?? $this->translations()->make(['locale' => $locale]);
        $translation->fill(Arr::except($values, 'slug'));

        if (in_array('slug', $this->translatedAttributes(), true)) {
            $text = ($values['slug'] ?? null) ?: $translation->slug ?: ($translation->{$this->slugSource()} ?? '');
            $translation->slug = static::uniqueSlug((string) $text, $locale, $translation->id);
        }

        $translation->save();

        return $translation;
    }

    /** @param  array<string, mixed>  $values */
    private function saveDerivedLocale(string $locale, string $sourceLocale, array $values): Translation
    {
        $locales = app(Locales::class);
        $source = $this->freshTranslation($sourceLocale);
        $translation = $this->freshTranslation($locale) ?? $this->translations()->make(['locale' => $locale]);
        $manual = $translation->manual_fields ?? [];

        foreach (Arr::except($values, 'slug') as $attribute => $value) {
            $generated = $source ? $locales->transliterate($locale, $source->getAttribute($attribute)) : null;
            $translation->setAttribute($attribute, $value);
            $manual = $value === $generated
                ? array_diff($manual, [$attribute])
                : array_merge($manual, [$attribute]);
        }

        $translation->manual_fields = array_values(array_unique($manual));

        if (in_array('slug', $this->translatedAttributes(), true)) {
            $translation->slug = $source->slug
                ?? static::uniqueSlug((string) ($translation->{$this->slugSource()} ?? ''), $locale, $translation->id);
        }

        $translation->save();

        return $translation;
    }

    /** Rebuild a derived locale from its source; hand-edited attributes and nothing-to-copy cases are left alone. */
    private function regenerate(string $locale): ?Translation
    {
        $locales = app(Locales::class);
        $source = $locales->derivedFrom($locale);
        $sourceTranslation = $source ? $this->freshTranslation($source) : null;
        if ($sourceTranslation === null) {
            return null; // no source yet: nothing is generated
        }

        $translation = $this->freshTranslation($locale) ?? $this->translations()->make(['locale' => $locale]);
        $manual = $translation->manual_fields ?? [];

        foreach ($this->translatedAttributes() as $attribute) {
            if ($attribute === 'slug') {
                $translation->slug = $sourceTranslation->slug;
            } elseif (! in_array($attribute, $manual, true)) {
                $translation->setAttribute($attribute, $locales->transliterate($locale, $sourceTranslation->getAttribute($attribute)));
            }
        }

        $translation->manual_fields = $manual;
        $translation->save();

        return $translation;
    }
}
