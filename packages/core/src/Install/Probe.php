<?php

namespace Codeart\Core\Install;

/** What the requirements check asks the server. A class of its own so tests can pretend any environment. */
class Probe
{
    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function extensionLoaded(string $name): bool
    {
        return extension_loaded($name);
    }

    public function writable(string $path): bool
    {
        return is_writable($path);
    }

    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /** @return int bytes, -1 = unlimited */
    public function memoryLimit(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $number = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    public function debugOn(): bool
    {
        return (bool) config('app.debug');
    }
}
