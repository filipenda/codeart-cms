<?php

namespace Codeart\Core\Settings;

use Closure;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Describes one setting. Registered through SettingsRegistry by core or by a module's service provider.
 *
 * `permission` = who may change it through SettingsManager; null means super-admin only.
 * `check` = cross-field validation: fn (mixed $value, Closure $read): ?string, where $read('other.key')
 * returns the value another setting will have after the same write; return an error message or null.
 */
final class SettingDefinition
{
    public readonly string $group;

    public readonly bool $secret;

    /**
     * @param  mixed|Closure  $default  a value, or a Closure evaluated on every read (config-backed defaults)
     * @param  list<mixed>  $rules  Laravel validation rules applied to the value
     * @param  list<mixed>  $itemRules  rules for each item of a list/object value
     * @param  list<string>  $options  allowed values of an enum
     */
    public function __construct(
        public readonly string $key,
        public readonly SettingType $type,
        public readonly mixed $default = null,
        public readonly array $rules = [],
        public readonly array $itemRules = [],
        public readonly ?string $permission = null,
        public readonly bool $translatable = false,
        bool $secret = false,
        public readonly string $label = '',
        public readonly string $description = '',
        public readonly array $options = [],
        ?string $group = null,
        public readonly ?string $module = null,
        public readonly ?Closure $check = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/', $key) !== 1) {
            throw new InvalidArgumentException("Setting key [{$key}] must look like group.name (lowercase).");
        }
        if ($module !== null && ! str_starts_with($key, $module.'.')) {
            throw new InvalidArgumentException("Setting [{$key}] of module [{$module}] must start with \"{$module}.\".");
        }
        if ($type === SettingType::Enum && $options === []) {
            throw new InvalidArgumentException("Enum setting [{$key}] needs options.");
        }

        $this->secret = $secret || $type === SettingType::Secret;
        $this->group = $group ?? explode('.', $key)[0];
    }

    public function default(): mixed
    {
        return $this->default instanceof Closure ? ($this->default)() : $this->default;
    }

    /**
     * Cast and validate a value against the type and rules. Returns the native value.
     *
     * @throws InvalidSettingValue
     */
    public function normalize(mixed $value): mixed
    {
        try {
            $value = $this->type->cast($value, $this);
        } catch (InvalidArgumentException $e) {
            throw new InvalidSettingValue($this->key, $e->getMessage());
        }

        if ($this->rules !== [] || $this->itemRules !== []) {
            // 'integer' makes min/max compare numbers instead of string lengths
            $rules = ['value' => $this->type === SettingType::Int ? ['integer', ...$this->rules] : $this->rules];
            if ($this->itemRules !== []) {
                $rules['value.*'] = $this->itemRules;
            }
            $validator = Validator::make(['value' => $value], array_filter($rules), [], ['value' => $this->key]);
            if ($validator->fails()) {
                // Secrets: say nothing about the value, not even in the rule message.
                throw new InvalidSettingValue($this->key, $this->secret ? 'does not meet the rules' : $validator->errors()->first());
            }
        }

        return $value;
    }
}
