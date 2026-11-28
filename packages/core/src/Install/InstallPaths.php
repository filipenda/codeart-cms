<?php

namespace Codeart\Core\Install;

/** Where the installer reads and writes. Every path comes from config, so tests run in a temporary directory. */
final readonly class InstallPaths
{
    public function __construct(
        public string $base,
        public string $env,
        public string $marker,
        public string $key,
    ) {}

    public static function fromConfig(): self
    {
        $base = rtrim((string) (config('codeart.install.base_path') ?: base_path()), '/');

        return new self(
            $base,
            (string) (config('codeart.install.env_file') ?: $base.'/.env'),
            (string) (config('codeart.install.marker') ?: $base.'/storage/app/installed'),
            (string) (config('codeart.install.key_file') ?: $base.'/install.key'),
        );
    }

    public function storage(): string
    {
        return $this->base.'/storage';
    }

    public function bootstrapCache(): string
    {
        return $this->base.'/bootstrap/cache';
    }
}
