<?php

namespace Codeart\Core\Theme;

use Codeart\Core\Auth\Admin;
use Codeart\Core\Settings\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Returns the CSS variable overrides for the design tokens.
 *
 * Values come from the database settings (theme.*) with the config defaults as fallback.
 * tokens.css stays the single source of truth for defaults: when a color equals its
 * brand default, no override is emitted.
 */
class ThemeSettings
{
    /**
     * @param  array<string, mixed>  $base  ink, paper, surface_light, surface_dark, min_contrast
     * @param  array<string, mixed>  $defaults  what tokens.css defines: primary / secondary
     */
    public function __construct(
        private readonly Settings $settings,
        private readonly array $base,
        private readonly array $defaults = [],
    ) {}

    /** light | dark | auto (auto follows the visitor's system preference, see tokens.css) */
    public function mode(): string
    {
        $mode = $this->settings->get('theme.mode');

        return in_array($mode, ['light', 'dark', 'auto'], true) ? $mode : 'light';
    }

    /** The signed-in user's saved choice (profile), or null: then the browser's choice or the site default applies. */
    public function userMode(): ?string
    {
        $user = request()->user(Admin::guardName());
        $mode = $user instanceof Model ? $user->getAttribute('theme_preference') : null;

        return in_array($mode, ['light', 'dark', 'auto'], true) ? $mode : null;
    }

    /** What the page starts with: the user's saved choice, else the site default. */
    public function effectiveMode(): string
    {
        return $this->userMode() ?? $this->mode();
    }

    /** Value for the <html> tag; see partials/theme-attributes. */
    public function htmlAttributes(): string
    {
        return 'data-theme="'.$this->effectiveMode().'"';
    }

    public function logoLight(): ?string
    {
        return $this->path('theme.logo_light');
    }

    public function logoDark(): ?string
    {
        return $this->path('theme.logo_dark');
    }

    public function favicon(): ?string
    {
        return $this->path('theme.favicon');
    }

    private function path(string $key): ?string
    {
        $path = $this->settings->get($key);

        return is_string($path) && $path !== '' ? $path : null;
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

    /**
     * Resolved colors for places where CSS variables do not work (e-mail). Every value is derived
     * from the theme settings and the brand-neutral base colors in config, never written down in a view.
     *
     * @return array{primary: string, on_primary: string, link: string, text: string, muted: string, card: string, background: string, border: string}
     */
    public function emailPalette(): array
    {
        $configured = $this->colors()['primary'] ?? null;
        $primary = Color::isHex($configured) ? Color::normalize($configured) : (Color::isHex($this->defaults['primary'] ?? null) ? Color::normalize($this->defaults['primary']) : $this->baseColor('ink'));
        $ink = $this->baseColor('ink');
        $card = $this->baseColor('surface_light');

        return [
            'primary' => $primary,
            'on_primary' => $this->onColor($primary),
            'link' => Color::ensureContrast($primary, $card, $ink, $this->minContrast()),
            'text' => $ink,
            'muted' => Color::mix($ink, $card, 0.4),
            'card' => $card,
            'background' => Color::mix($card, $ink, 0.06),
            'border' => Color::mix($card, $ink, 0.14),
        ];
    }

    /** CSS to place in an inline <style> block. Empty string when nothing is overridden. */
    public function css(): string
    {
        // The key holds every input, so a changed setting simply produces a new entry (no invalidation needed).
        $key = 'codeart.theme.css.'.md5((string) json_encode([$this->colors(), $this->base, $this->defaults]));

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
            $dark = $this->declarations($overrides['dark']);
            $css .= ':root[data-theme="dark"]{'.$dark.'}';
            // mode "auto": the same dark values, only while the visitor's system prefers dark
            $css .= '@media (prefers-color-scheme:dark){:root[data-theme="auto"]{'.$dark.'}}';
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
        $value = $this->colors()[$key] ?? null;
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

    /** @return array{primary: mixed, secondary: mixed} */
    private function colors(): array
    {
        return ['primary' => $this->settings->get('theme.primary'), 'secondary' => $this->settings->get('theme.secondary')];
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
