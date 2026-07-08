<?php

declare(strict_types=1);

namespace App\Core\Settings;

/**
 * Holds the typed setting definitions each module contributes. The DB stores
 * only deltas from these defaults, so an empty settings table is fully runnable.
 */
final class SettingsRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function register(SettingDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function definition(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    /** @return array<string, SettingDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }
}
