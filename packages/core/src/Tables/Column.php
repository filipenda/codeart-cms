<?php

namespace Codeart\Core\Tables;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * One column of a DataTable: key, label, the DB expression that sorts, searches and filters it, and the way a cell is
 * drawn (`type`). Every column is sortable, searchable and filterable unless it says otherwise.
 *
 * Cell values (what `value()` returns) per type:
 *  text, number, date (Carbon or string), boolean ... the plain value;
 *  status, badge ... the raw value, shown through `options()` (value => [label, tone]);
 *  avatar ... a name, or ['name' =>, 'subtitle' =>, 'image' =>] (from `using()`);
 *  title-subtitle ... a title, or ['title' =>, 'subtitle' =>, 'url' =>];
 *  thumbnail ... an image URL;
 *  language-chips ... a list of locale codes, or [code => present?].
 */
final class Column
{
    public const array TYPES = ['text', 'number', 'date', 'boolean', 'status', 'badge', 'avatar', 'title-subtitle', 'thumbnail', 'language-chips', 'actions'];

    public bool $sortable = true;

    public bool $searchable = true;

    public bool $filterable = true;

    public bool $exportable = true;

    public ?Closure $using = null;

    public ?Closure $exportUsing = null;

    /** @var array<string, array{0: string, 1: string}> value => [label, tone] */
    public array $options = [];

    public string $dateFormat = 'd.m.Y. H:i';

    /** @var (Closure(string): array<string, string>)|null locale => (raw value => translated text); see `searchTranslated()` */
    public ?Closure $translations = null;

    public function __construct(public readonly string $key, public readonly string $label, public readonly string $type = 'text', public readonly string $expression = '')
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown column type {$type}");
        }
    }

    public static function make(string $key, string $label, string $type = 'text', ?string $expression = null): self
    {
        return new self($key, $label, $type, $expression ?? $key);
    }

    public static function text(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'text', $expression);
    }

    public static function number(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'number', $expression);
    }

    public static function date(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'date', $expression);
    }

    public static function boolean(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'boolean', $expression);
    }

    /** @param array<string, array{0: string, 1: string}> $options value => [label, tone] */
    public static function status(string $key, string $label, array $options = [], ?string $expression = null): self
    {
        return self::make($key, $label, 'status', $expression)->options($options);
    }

    /** @param array<string, array{0: string, 1: string}> $options value => [label, tone] */
    public static function badge(string $key, string $label, array $options = [], ?string $expression = null): self
    {
        return self::make($key, $label, 'badge', $expression)->options($options);
    }

    public static function avatar(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'avatar', $expression);
    }

    public static function titleSubtitle(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'title-subtitle', $expression);
    }

    public static function thumbnail(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'thumbnail', $expression)->sortable(false)->searchable(false)->filterable(false);
    }

    public static function languageChips(string $key, string $label, ?string $expression = null): self
    {
        return self::make($key, $label, 'language-chips', $expression)->sortable(false)->searchable(false)->filterable(false);
    }

    public function sortable(bool $on = true): self
    {
        $this->sortable = $on;

        return $this;
    }

    public function searchable(bool $on = true): self
    {
        $this->searchable = $on;

        return $this;
    }

    public function filterable(bool $on = true): self
    {
        $this->filterable = $on;

        return $this;
    }

    public function exportable(bool $on = true): self
    {
        $this->exportable = $on;

        return $this;
    }

    /** What the cell shows, when it is not simply `data_get($row, key)`: `fn (Model $row): mixed`. */
    public function using(Closure $using): self
    {
        $this->using = $using;

        return $this;
    }

    /** The CSV value, when it differs from the displayed one: `fn (Model $row): scalar|null`. */
    public function exportUsing(Closure $using): self
    {
        $this->exportUsing = $using;

        return $this;
    }

    /**
     * The cell shows a translated text for a raw value (an event key, a status ...). Search must find what the user SEES,
     * so the term is also matched against these texts in the current locale plus sr, sr-Cyrl and en, and every raw value
     * whose text contains it is searched together with the term itself. `$labels` is `fn (string $locale): array<raw, text>`.
     *
     * @param  Closure(string): array<string, string>  $labels
     */
    public function searchTranslated(Closure $labels): self
    {
        $this->translations = $labels;

        return $this;
    }

    /** Same, for a column whose `options()` labels are already the translated texts of the current locale only. */
    public function searchByOptions(): self
    {
        return $this->searchTranslated(fn (string $locale): array => array_map(fn (array $o): string => $o[0], $this->options));
    }

    /**
     * Raw values whose translated text (in any searched locale) contains the word.
     *
     * @return list<string>
     */
    public function keysMatching(string $word): array
    {
        if ($this->translations === null) {
            return [];
        }
        $locales = array_values(array_unique([app()->getLocale(), 'sr', 'sr-Cyrl', 'en']));
        $keys = [];
        foreach ($locales as $locale) {
            foreach (($this->translations)($locale) as $raw => $text) {
                if (mb_stripos((string) $text, $word) !== false) {
                    $keys[(string) $raw] = true;
                }
            }
        }

        return array_map(strval(...), array_keys($keys));
    }

    /** @param array<string, array{0: string, 1: string}> $options value => [label, tone] */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function dateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function value(Model $row): mixed
    {
        return $this->using !== null ? ($this->using)($row) : data_get($row, $this->key);
    }

    /** Plain CSV value of a cell. */
    public function exportValue(Model $row): string|int|float|null
    {
        if ($this->exportUsing !== null) {
            $value = ($this->exportUsing)($row);

            return is_scalar($value) || $value === null ? $value : (string) json_encode($value);
        }
        $value = $this->value($row);

        return match (true) {
            $value === null => null,
            is_bool($value) => __($value ? 'codeart::table.yes' : 'codeart::table.no'),
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $this->type === 'status' || $this->type === 'badge' => isset($this->options[(string) $value]) ? $this->options[(string) $value][0] : (string) $value,
            $this->type === 'boolean' => __($value ? 'codeart::table.yes' : 'codeart::table.no'),
            is_array($value) => $this->exportArray($value),
            is_scalar($value) => $value,
            $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }

    /** @param array<array-key, mixed> $value */
    private function exportArray(array $value): string
    {
        if ($this->type === 'language-chips') {
            return implode(' ', array_map(strval(...), array_is_list($value) ? $value : array_keys(array_filter($value))));
        }
        $text = $value['title'] ?? $value['name'] ?? null;

        return is_scalar($text) ? (string) $text : (string) json_encode($value);
    }
}
