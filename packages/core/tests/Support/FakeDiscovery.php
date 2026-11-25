<?php

namespace Codeart\Core\Tests\Support;

use Codeart\Core\Modules\ModuleDiscovery;

/** Discovery over the fixture modules instead of Composer's installed metadata. */
class FakeDiscovery extends ModuleDiscovery
{
    /** @var array<string, string> fixture dir => version */
    public static array $modules = [];

    protected function installedPackages(): array
    {
        $found = [];
        foreach (self::$modules as $dir => $version) {
            $path = dirname(__DIR__).'/Fixtures/modules/'.$dir;
            $found[] = [
                'name' => 'fixture/module-'.$dir,
                'path' => $path,
                'version' => $version,
            ];
        }

        return $found;
    }
}
