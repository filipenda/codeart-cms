<?php

namespace Codeart\Core\Tables;

use InvalidArgumentException;

/** Table key => definition class. Modules register theirs in their provider; unknown keys are a 404 at the endpoints. */
final class TableRegistry
{
    /** @var array<string, class-string<TableDefinition>> */
    private array $tables = [];

    /** @param class-string<TableDefinition> $definition */
    public function register(string $key, string $definition): void
    {
        if (! is_subclass_of($definition, TableDefinition::class)) {
            throw new InvalidArgumentException("{$definition} must implement ".TableDefinition::class);
        }
        $this->tables[$key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->tables[$key]);
    }

    public function get(string $key): ?DataTable
    {
        return isset($this->tables[$key]) ? app($this->tables[$key])->table() : null;
    }
}
