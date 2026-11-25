<?php

namespace Codeart\Core\Translations;

use InvalidArgumentException;

/** Reads the `codeart.locales` config (later: database settings). Content languages only, not the admin UI language. */
class Locales
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function default(): string
    {
        return (string) ($this->config['default'] ?? 'sr');
    }

    /** @return list<string> */
    public function enabled(): array
    {
        return array_values((array) ($this->config['enabled'] ?? [$this->default()]));
    }

    public function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabled(), true);
    }

    public function assertEnabled(string $locale): void
    {
        if (! $this->isEnabled($locale)) {
            throw new InvalidArgumentException("Locale [{$locale}] is not enabled.");
        }
    }

    /** The content locale for this request: the app locale if enabled, else the default. */
    public function current(): string
    {
        $locale = app()->getLocale();

        return $this->isEnabled($locale) ? $locale : $this->default();
    }

    public function label(string $locale): string
    {
        return (string) ($this->definition($locale)['label'] ?? $locale);
    }

    public function script(string $locale): ?string
    {
        return $this->definition($locale)['script'] ?? null;
    }

    /** The source locale a derived locale is generated from, or null for manual locales. */
    public function derivedFrom(string $locale): ?string
    {
        $source = $this->definition($locale)['derived_from'] ?? null;

        return is_string($source) ? $source : null;
    }

    /**
     * Enabled locales generated from $source.
     *
     * @return list<string>
     */
    public function derivedLocales(string $source): array
    {
        return array_values(array_filter($this->enabled(), fn (string $l) => $this->derivedFrom($l) === $source));
    }

    /** Transliterate text into a derived locale's script. */
    public function transliterate(string $locale, ?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $class = $this->definition($locale)['transliterator'] ?? null;
        if (! is_string($class)) {
            throw new InvalidArgumentException("Locale [{$locale}] has no transliterator.");
        }

        return app($class)->transliterate($text);
    }

    /**
     * Locales to try for $locale, in order: itself, its configured fallbacks, then the default.
     * Only enabled locales.
     *
     * @return list<string>
     */
    public function chain(string $locale): array
    {
        $chain = [$locale, ...(array) ($this->config['fallbacks'][$locale] ?? []), $this->default()];

        return array_values(array_filter(array_unique($chain), fn (string $l) => $this->isEnabled($l)));
    }

    /** @return array<string, mixed> */
    private function definition(string $locale): array
    {
        return (array) ($this->config['available'][$locale] ?? []);
    }
}
