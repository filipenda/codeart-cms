<?php

namespace Codeart\Core\Theme;

/**
 * The built CSS and JS (resources/dist, published to public/vendor/codeart). The version in the URL is a hash of
 * the file CONTENT, so browsers refetch exactly when a file changed and never otherwise.
 */
class Assets
{
    /** @var array<string, string> */
    private array $versions = [];

    /** The folder shipped inside the package. */
    public static function packagePath(string $file = ''): string
    {
        return dirname(__DIR__, 2).'/resources/dist'.($file === '' ? '' : '/'.$file);
    }

    /** Public URL with the content hash, e.g. /vendor/codeart/codeart.css?v=3fa9c1d2e4b7 */
    public function url(string $file): string
    {
        return asset('vendor/codeart/'.$file).'?v='.$this->version($file);
    }

    /** First 12 hex characters of the content hash; the published copy is hashed when it exists, else the package's. */
    public function version(string $file): string
    {
        return $this->versions[$file] ??= $this->hash($file);
    }

    private function hash(string $file): string
    {
        $published = public_path('vendor/codeart/'.$file);
        $path = is_file($published) ? $published : self::packagePath($file);

        return is_file($path) ? substr((string) hash_file('xxh128', $path), 0, 12) : '0';
    }
}
