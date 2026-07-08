<?php

declare(strict_types=1);

namespace App\Core\Settings;

/** The value type of a setting; the database stores only overrides of the default. */
enum SettingType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case FloatType = 'float';
    case ArrayType = 'array';

    public function accepts(mixed $value): bool
    {
        return match ($this) {
            self::String => is_string($value),
            self::Integer => is_int($value),
            self::Boolean => is_bool($value),
            self::FloatType => is_float($value) || is_int($value),
            self::ArrayType => is_array($value),
        };
    }
}
