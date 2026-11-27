<?php

namespace Codeart\Core\Tables;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A query constraint driven by `filter[key]`. Declared filters get a control in the toolbar; every column also has an
 * implicit filter of the same kind (see `Column`), reachable through the URL and the "more filters" panel.
 * Invalid values normalise to null and are ignored.
 */
final class Filter
{
    public const array TYPES = ['select', 'boolean', 'date-range', 'number-range', 'text'];

    public ?Closure $apply = null;

    /** @param array<string, string> $options value => label (select) */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly array $options = [],
        public readonly string $expression = '',
    ) {}

    /** @param array<string, string> $options value => label */
    public static function select(string $key, string $label, array $options, ?string $expression = null): self
    {
        return new self($key, $label, 'select', $options, $expression ?? $key);
    }

    /**
     * A select whose options are statuses (same behaviour as `select`; the name documents intent).
     *
     * @param  array<string, string>  $options
     */
    public static function status(string $key, string $label, array $options, ?string $expression = null): self
    {
        return self::select($key, $label, $options, $expression);
    }

    public static function boolean(string $key, string $label, ?string $expression = null): self
    {
        return new self($key, $label, 'boolean', [], $expression ?? $key);
    }

    public static function dateRange(string $key, string $label, ?string $expression = null): self
    {
        return new self($key, $label, 'date-range', [], $expression ?? $key);
    }

    public static function numberRange(string $key, string $label, ?string $expression = null): self
    {
        return new self($key, $label, 'number-range', [], $expression ?? $key);
    }

    public static function text(string $key, string $label, ?string $expression = null): self
    {
        return new self($key, $label, 'text', [], $expression ?? $key);
    }

    /** Replace the default constraint: `fn (Builder $query, mixed $value): void`, `$value` already validated. */
    public function using(Closure $apply): self
    {
        $this->apply = $apply;

        return $this;
    }

    /**
     * The cleaned value, or null when the raw one is not valid for this filter.
     *
     * @return string|bool|array<string, string>|null
     */
    public function normalize(mixed $raw): string|bool|array|null
    {
        return match ($this->type) {
            'select' => $this->cleanSelect($raw),
            'boolean' => $raw === '1' || $raw === '0' || $raw === 1 || $raw === 0 ? (bool) (int) $raw : null,
            'date-range' => $this->range($raw, fn (mixed $v): ?string => $this->date($v)),
            'number-range' => $this->range($raw, fn (mixed $v): ?string => is_string($v) && is_numeric($v) && strlen($v) <= 20 ? $v : null, true),
            default => is_string($raw) && trim($raw) !== '' ? mb_substr(trim($raw), 0, 200) : null,
        };
    }

    /**
     * Add the constraint to `$query`. `$value` must come from `normalize()`.
     *
     * @param  Builder<Model>  $query
     */
    public function applyTo(DataTable $table, Builder $query, mixed $value): void
    {
        if ($this->apply !== null) {
            ($this->apply)($query, $value);

            return;
        }
        $column = $table->sql($this->expression);

        match ($this->type) {
            'select' => $query->where($column, '=', $value),
            'boolean' => $query->where($column, '=', $value ? 1 : 0),
            'date-range' => $this->dates($query, $column, (array) $value),
            'number-range' => $this->numbers($query, $column, (array) $value),
            default => $query->whereRaw($column->getValue($query->getGrammar())." LIKE ? ESCAPE '!'", ['%'.self::escapeLike((string) $value).'%']),
        };
    }

    /** Escape % _ and the escape character itself, for `LIKE ? ESCAPE '!'`. */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $range
     */
    private function dates(Builder $query, Expression $column, array $range): void
    {
        if (isset($range['from'])) {
            $query->where($column, '>=', $range['from'].' 00:00:00');
        }
        if (isset($range['to'])) {
            $query->where($column, '<', (new DateTimeImmutable($range['to']))->modify('+1 day')->format('Y-m-d').' 00:00:00');
        }
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $range
     */
    private function numbers(Builder $query, Expression $column, array $range): void
    {
        if (isset($range['from'])) {
            $query->where($column, '>=', $range['from']);
        }
        if (isset($range['to'])) {
            $query->where($column, '<=', $range['to']);
        }
    }

    /** @return array{from?: string, to?: string}|null */
    private function range(mixed $raw, Closure $clean, bool $scalarMeansEqual = false): ?array
    {
        if ($scalarMeansEqual && is_string($raw) && ($value = $clean($raw)) !== null) {
            return ['from' => $value, 'to' => $value];
        }
        if (! is_array($raw)) {
            return null;
        }
        $out = [];
        foreach (['from', 'to'] as $edge) {
            if (isset($raw[$edge]) && ($value = $clean($raw[$edge])) !== null) {
                $out[$edge] = $value;
            }
        }

        return $out === [] ? null : $out;
    }

    private function cleanSelect(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        if ($this->options !== []) {
            return array_key_exists($raw, $this->options) ? $raw : null;
        }

        return mb_strlen($raw) <= 200 ? $raw : null;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
