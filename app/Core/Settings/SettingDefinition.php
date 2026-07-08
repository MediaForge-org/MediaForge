<?php

declare(strict_types=1);

namespace App\Core\Settings;

/** A registered setting: its namespaced key, code default and value type. */
final readonly class SettingDefinition
{
    public function __construct(
        public string $key,
        public mixed $default,
        public SettingType $type,
        public string $description = '',
    ) {}
}
