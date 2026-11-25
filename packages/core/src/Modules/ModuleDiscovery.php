<?php

namespace Codeart\Core\Modules;

use Composer\InstalledVersions;

/**
 * Finds installed packages of type "codeart-module" through Composer's installed metadata
 * (vendor/composer/installed.php). Pure file reads: works after an FTP upload, no shell.
 */
class ModuleDiscovery
{
    /** @var array<string, InstalledModule>|null */
    private ?array $modules = null;

    /** @var array<string, string> */
    private array $problems = [];

    /** @return array<string, InstalledModule> keyed by module name */
    public function discover(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $this->modules = [];
        $this->problems = [];

        foreach ($this->installedPackages() as $package) {
            try {
                $manifest = ModuleManifest::fromFile($package['path'].'/module.json');
                if ($manifest->package !== $package['name']) {
                    throw new InvalidManifest("module.json says package \"{$manifest->package}\" but it is installed as \"{$package['name']}\".");
                }
                if (isset($this->modules[$manifest->name])) {
                    throw new InvalidManifest("module name \"{$manifest->name}\" is already provided by {$this->modules[$manifest->name]->manifest->package}.");
                }
            } catch (InvalidManifest $e) {
                $this->problems[$package['name']] = $e->getMessage();

                continue;
            }

            $this->modules[$manifest->name] = new InstalledModule($manifest, $package['path'], $package['version']);
        }

        return $this->modules;
    }

    /** @return array<string, string> package => why its manifest was rejected */
    public function problems(): array
    {
        $this->discover();

        return $this->problems;
    }

    /** @return list<array{name: string, path: string, version: string}> */
    protected function installedPackages(): array
    {
        $found = [];
        foreach (InstalledVersions::getInstalledPackagesByType('codeart-module') as $name) {
            $path = InstalledVersions::getInstallPath($name);
            if ($path === null) {
                continue;
            }
            $found[] = [
                'name' => $name,
                'path' => rtrim($path, '/'),
                'version' => InstalledVersions::getPrettyVersion($name) ?? 'unknown',
            ];
        }

        return $found;
    }
}
