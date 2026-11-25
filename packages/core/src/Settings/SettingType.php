<?php

namespace Codeart\Core\Settings;

use Codeart\Core\Theme\Color;
use InvalidArgumentException;

enum SettingType: string
{
    case String = 'string';
    case Int = 'int';
    case Bool = 'bool';
    case Color = 'color';
    case Enum = 'enum';
    case Json = 'json';
    case Image = 'image';
    case Secret = 'secret';

    /**
     * Cast a raw value (also from CLI strings) to the native type.
     *
     * @throws InvalidArgumentException with a reason that never contains the value
     */
    public function cast(mixed $value, SettingDefinition $definition): mixed
    {
        switch ($this) {
            case self::String:
            case self::Secret:
                return is_string($value) ? $value : throw new InvalidArgumentException('must be a string');
            case self::Int:
                if (is_int($value)) {
                    return $value;
                }

                return is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1
                    ? (int) $value
                    : throw new InvalidArgumentException('must be an integer');
            case self::Bool:
                return match (true) {
                    is_bool($value) => $value,
                    $value === 1, $value === '1', $value === 'true' => true,
                    $value === 0, $value === '0', $value === 'false' => false,
                    default => throw new InvalidArgumentException('must be true or false'),
                };
            case self::Color:
                return Color::isHex($value) ? Color::normalize($value) : throw new InvalidArgumentException('must be a hex color (3 or 6 digits)');
            case self::Enum:
                return is_string($value) && in_array($value, $definition->options, true)
                    ? $value
                    : throw new InvalidArgumentException('must be one of: '.implode(', ', $definition->options));
            case self::Json:
                return is_array($value) ? $value : throw new InvalidArgumentException('must be a list or an object');
            case self::Image:
                return is_string($value) && preg_match('#^(?!/)[A-Za-z0-9._/-]{1,255}$#', $value) === 1 && ! str_contains($value, '..')
                    ? $value
                    : throw new InvalidArgumentException('must be a relative file path');
        }
    }
}
