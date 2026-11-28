<?php

namespace Codeart\Core\Install;

class Requirements
{
    public const string MIN_PHP = '8.4.1';

    public const int MIN_MEMORY = 128 * 1024 * 1024;

    /** @var list<string> */
    public const array EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'intl', 'fileinfo', 'zip', 'ctype', 'json', 'tokenizer', 'xml', 'curl'];

    public function __construct(private readonly InstallPaths $paths, private readonly Probe $probe) {}

    /** @return list<Requirement> */
    public function check(): array
    {
        $list = [new Requirement('php', version_compare($this->probe->phpVersion(), self::MIN_PHP, '>='), replace: ['min' => self::MIN_PHP, 'have' => $this->probe->phpVersion()])];

        foreach (self::EXTENSIONS as $extension) {
            $list[] = new Requirement('ext', $this->probe->extensionLoaded($extension), replace: ['name' => $extension]);
        }
        $list[] = new Requirement('image', $this->probe->extensionLoaded('gd') || $this->probe->extensionLoaded('imagick'));

        $envDir = dirname($this->paths->env);
        $list[] = new Requirement('storage', $this->probe->writable($this->paths->storage()));
        $list[] = new Requirement('cache', $this->probe->writable($this->paths->bootstrapCache()));
        $list[] = new Requirement('env', $this->probe->exists($this->paths->env) ? $this->probe->writable($this->paths->env) : $this->probe->writable($envDir));
        $list[] = new Requirement('layout', $this->probe->exists($this->paths->base.'/vendor/autoload.php') && $this->probe->exists($this->paths->base.'/bootstrap/app.php'));
        $memory = $this->probe->memoryLimit();
        $list[] = new Requirement('memory', $memory === -1 || $memory >= self::MIN_MEMORY, replace: ['min' => (int) (self::MIN_MEMORY / 1024 ** 2)]);
        $list[] = new Requirement('debug', ! $this->probe->debugOn(), required: false);

        return $list;
    }

    /** @param list<Requirement>|null $list */
    public function passes(?array $list = null): bool
    {
        foreach ($list ?? $this->check() as $item) {
            if ($item->required && ! $item->ok) {
                return false;
            }
        }

        return true;
    }
}
