<?php

namespace Codeart\Core\Admin\Search;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Throwable;

/** Providers of command palette results. Core ships the menu provider; modules add theirs from their service provider. */
class SearchRegistry
{
    /** @var array<class-string<SearchProvider>, true> */
    private array $providers = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<SearchProvider>  ...$providers */
    public function add(string ...$providers): void
    {
        foreach ($providers as $provider) {
            $this->providers[$provider] = true;
        }
    }

    /**
     * @return list<array{label: string, url: string, icon: string, group: ?string}>
     */
    public function results(?Authenticatable $user): array
    {
        $out = [];
        foreach (array_keys($this->providers) as $class) {
            try {
                foreach ($this->container->make($class)->results($user) as $result) {
                    $out[] = $result->toArray();
                }
            } catch (Throwable $e) {
                report($e); // a broken provider must not break every page
            }
        }

        return $out;
    }
}
