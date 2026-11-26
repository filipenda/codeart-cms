<?php

namespace Codeart\Core\Admin;

/**
 * Inline SVG icons: the Lucide set shipped with core (resources/icons/lucide.json, built from scripts/icons.txt)
 * plus anything a module registers. Unknown names render nothing (never a broken page).
 */
class Icons
{
    /** @var array<string, string>|null */
    private ?array $icons = null;

    /** @var array<string, string> */
    private array $extra = [];

    /** A module's own icon: the inner SVG markup (24x24 viewBox, stroke style like Lucide). */
    public function register(string $name, string $innerSvg): void
    {
        $this->extra[$name] = $innerSvg;
    }

    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function get(string $name): ?string
    {
        $this->icons ??= (array) json_decode((string) file_get_contents(dirname(__DIR__, 2).'/resources/icons/lucide.json'), true);

        return $this->extra[$name] ?? $this->icons[$name] ?? null;
    }
}
