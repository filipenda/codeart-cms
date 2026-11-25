<?php

namespace Codeart\Core\Theme;

use Illuminate\Support\Facades\Cache;

/**
 * Returns the CSS variable overrides for the design tokens.
 *
 * Today the values come from config; later from the database settings screen.
 * tokens.css stays the single source of truth for defaults: when a color
 * equals its brand default, no override is emitted.
 */
class ThemeSettings
{
    /**
     * @param  array<string, mixed>  $settings  primary, secondary, default_mode, logo
     * @param  array<string, mixed>  $base  ink, paper, surface_light, surface_dark, min_contrast
     * @param  array<string, mixed>  $defaults  brand default primary / secondary
     */
    public function __construct(
        private readonly array $settings,
        private readonly array $base,
        private readonly array $defaults = [],
    ) {}

    public function defaultMode(): string
    {
        return ($this->settings['default_mode'] ?? 'light') === 'dark' ? 'dark' : 'light';
    }

    public function logo(): ?string
    {
        $logo = $this->settings['logo'] ?? null;

        return is_string($logo) && $logo !== '' ? $logo : null;
    }

    /**
     * Variable overrides per scope: 'light' (:root) and 'dark' ([data-theme="dark"]).
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public function overrides(): array
    {
        $light = [];
        $dark = [];

        $primary = $this->customColor('primary');
        if ($primary !== null) {
            $shared = $this->primaryShades($primary);
            $light = $dark = $shared;
            $light['--color-link'] = Color::ensureContrast(
                $primary,
                $this->baseColor('surface_light'),
                $this->baseColor('ink'),
                $this->minContrast(),
            );
            $dark['--color-link'] = Color::ensureContrast(
                $primary,
                $this->baseColor('surface_dark'),
                $this->baseColor('paper'),
                $this->minContrast(),
            );
        }

        $secondary = $this->customColor('secondary');
        if ($secondary !== null) {
            $shades = [
                '--color-secondary' => $secondary,
                '--color-on-secondary' => $this->onColor($secondary),
            ];
            $light += $shades;
            $dark += $shades;
        }

        return ['light' => $light, 'dark' => $dark];
    }

    /** CSS to place in an inline <style> block. Empty string when nothing is overridden. */
    public function css(): string
    {
        $key = 'codeart.theme.css.'.md5((string) json_encode([$this->settings, $this->base, $this->defaults]));

        return Cache::rememberForever($key, fn (): string => $this->buildCss());
    }

    private function buildCss(): string
    {
        $overrides = $this->overrides();
        $css = '';

        // :root:root / :root[data-theme] beat the equal-specificity rules in tokens.css regardless of load order.
        if ($overrides['light'] !== []) {
            $css .= ':root:root{'.$this->declarations($overrides['light']).'}';
        }
        if ($overrides['dark'] !== []) {
            $css .= ':root[data-theme="dark"]{'.$this->declarations($overrides['dark']).'}';
        }

        return $css;
    }

    /** @param  array<string, string>  $vars */
    private function declarations(array $vars): string
    {
        $out = '';
        foreach ($vars as $name => $value) {
            $out .= $name.':'.$value.';';
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function primaryShades(string $primary): array
    {
        return [
            '--color-primary' => $primary,
            '--color-primary-hover' => Color::mix($primary, $this->baseColor('ink'), 0.15),
            '--color-on-primary' => $this->onColor($primary),
        ];
    }

    private function onColor(string $background): string
    {
        return Color::readableOn($background, $this->baseColor('ink'), $this->baseColor('paper'));
    }

    /** A valid color that differs from its brand default, normalized; otherwise null. */
    private function customColor(string $key): ?string
    {
        $value = $this->settings[$key] ?? null;
        if (! Color::isHex($value)) {
            return null;
        }
        $value = Color::normalize($value);

        $default = $this->defaults[$key] ?? null;
        if (Color::isHex($default) && Color::normalize($default) === $value) {
            return null;
        }

        return $value;
    }

    private function baseColor(string $key): string
    {
        $value = $this->base[$key] ?? null;

        if (Color::isHex($value)) {
            return Color::normalize($value);
        }

        return $key === 'paper' || $key === 'surface_light' ? Color::fromRgb(255, 255, 255) : Color::fromRgb(0, 0, 0);
    }

    private function minContrast(): float
    {
        return (float) ($this->base['min_contrast'] ?? 4.5);
    }
}
