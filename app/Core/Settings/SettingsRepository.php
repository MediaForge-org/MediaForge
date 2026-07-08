<?php

declare(strict_types=1);

namespace App\Core\Settings;

/**
 * Reads effective settings: a registered default overlaid with the DB override
 * if present. Writes go through the UpdateSetting action, never here.
 */
final class SettingsRepository
{
    public function __construct(private readonly SettingsRegistry $registry) {}

    public function get(string $key): mixed
    {
        $override = Setting::query()->find($key);

        if ($override !== null) {
            return $override->value;
        }

        return $this->registry->definition($key)?->default;
    }

    /** @return array<string, mixed> effective values (defaults + overrides) */
    public function all(): array
    {
        $values = [];

        foreach ($this->registry->all() as $key => $definition) {
            $values[$key] = $definition->default;
        }

        foreach (Setting::query()->get() as $setting) {
            $values[$setting->key] = $setting->value;
        }

        return $values;
    }
}
