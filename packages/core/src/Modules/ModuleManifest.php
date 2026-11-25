<?php

namespace Codeart\Core\Modules;

use JsonException;

/**
 * Typed, validated view of a module's module.json.
 * The version is deliberately absent: it comes from Composer's installed metadata.
 */
final readonly class ModuleManifest
{
    private const array KNOWN_KEYS = [
        'name', 'package', 'title', 'description', 'requires', 'permissions',
        'menu', 'migrations', 'provider', 'license',
    ];

    /**
     * @param  array<string, string>  $requires  module name => version constraint
     * @param  list<string>  $permissions
     * @param  list<MenuItem>  $menu
     */
    public function __construct(
        public string $name,
        public string $package,
        public string $title,
        public string $description,
        public array $requires,
        public array $permissions,
        public array $menu,
        public ?string $migrations,
        public string $provider,
        public ?string $license = null,
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidManifest("module.json not found at {$path}.");
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidManifest("module.json at {$path} is not valid JSON: {$e->getMessage()}");
        }

        if (! is_array($data)) {
            throw new InvalidManifest("module.json at {$path} must contain a JSON object.");
        }

        return self::fromArray($data);
    }

    /** @param  array<mixed>  $data */
    public static function fromArray(array $data): self
    {
        $label = is_string($data['name'] ?? null) ? "module \"{$data['name']}\"" : 'module.json';

        foreach (array_diff(array_keys($data), self::KNOWN_KEYS) as $unknown) {
            throw new InvalidManifest("{$label}: unknown field \"{$unknown}\".");
        }

        $name = self::string($data, 'name', $label);
        if (preg_match('/^[a-z][a-z0-9-]*$/', $name) !== 1) {
            throw new InvalidManifest("{$label}: \"name\" must be a lowercase slug (letters, digits, dashes), got \"{$name}\".");
        }

        $package = self::string($data, 'package', $label);
        if (preg_match('#^[a-z0-9]([a-z0-9_.-]*[a-z0-9])?/[a-z0-9]([a-z0-9_.-]*[a-z0-9])?$#', $package) !== 1) {
            throw new InvalidManifest("{$label}: \"package\" must look like vendor/name, got \"{$package}\".");
        }

        $requires = [];
        foreach (self::optionalArray($data, 'requires', $label) as $module => $constraint) {
            if (! is_string($module) || ! is_string($constraint) || $constraint === '') {
                throw new InvalidManifest("{$label}: \"requires\" must map module names to version constraints, e.g. {\"pages\": \"^1.0\"}.");
            }
            $requires[$module] = $constraint;
        }

        $permissions = [];
        foreach (self::optionalArray($data, 'permissions', $label) as $permission) {
            if (! is_string($permission) || ! str_starts_with($permission, $name.'.') || strlen($permission) <= strlen($name) + 1) {
                throw new InvalidManifest("{$label}: every permission must be a string starting with \"{$name}.\" (e.g. \"{$name}.view\").");
            }
            $permissions[] = $permission;
        }

        $menu = [];
        foreach (self::optionalArray($data, 'menu', $label) as $i => $item) {
            $menu[] = self::menuItem($item, $i, $name, $permissions, $label);
        }

        $migrations = $data['migrations'] ?? null;
        if ($migrations !== null && (! is_string($migrations) || $migrations === '' || str_starts_with($migrations, '/') || str_contains($migrations, '..'))) {
            throw new InvalidManifest("{$label}: \"migrations\" must be a relative path inside the package, e.g. \"database/migrations\".");
        }

        $provider = self::string($data, 'provider', $label);

        $license = $data['license'] ?? null;
        if ($license !== null && ! is_string($license)) {
            throw new InvalidManifest("{$label}: \"license\" must be a string.");
        }

        return new self(
            $name,
            $package,
            self::string($data, 'title', $label),
            self::string($data, 'description', $label),
            $requires,
            $permissions,
            $menu,
            $migrations,
            $provider,
            $license,
        );
    }

    /** @param  array<mixed>  $data */
    private static function string(array $data, string $key, string $label): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidManifest("{$label}: \"{$key}\" is required and must be a non-empty string.");
        }

        return $value;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private static function optionalArray(array $data, string $key, string $label): array
    {
        $value = $data[$key] ?? [];
        if (! is_array($value)) {
            throw new InvalidManifest("{$label}: \"{$key}\" must be a list/object.");
        }

        return $value;
    }

    /** @param  list<string>  $permissions */
    private static function menuItem(mixed $item, int|string $i, string $name, array $permissions, string $label): MenuItem
    {
        if (! is_array($item)) {
            throw new InvalidManifest("{$label}: menu item #{$i} must be an object.");
        }

        $where = "{$label}: menu item #{$i}";
        foreach (['label', 'route', 'icon'] as $key) {
            if (! is_string($item[$key] ?? null) || $item[$key] === '') {
                throw new InvalidManifest("{$where}: \"{$key}\" is required and must be a non-empty string.");
            }
        }
        if (! is_int($item['order'] ?? null)) {
            throw new InvalidManifest("{$where}: \"order\" is required and must be an integer.");
        }

        $permission = $item['permission'] ?? null;
        if ($permission !== null && ! is_string($permission)) {
            throw new InvalidManifest("{$where}: \"permission\" must be a string.");
        }
        if ($permission !== null && ! in_array($permission, $permissions, true)) {
            throw new InvalidManifest("{$where}: permission \"{$permission}\" is not declared in this module's \"permissions\".");
        }

        return new MenuItem($item['label'], $item['route'], $permission, $item['icon'], $item['order'], $name);
    }
}
