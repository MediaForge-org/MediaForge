<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Settings\SettingDefinition;
use App\Core\Settings\SettingsRegistry;
use Inertia\Inertia;
use Inertia\Response;

final class SettingsController extends Controller
{
    public function index(SettingsRegistry $settings): Response
    {
        $definitions = array_values(array_map(
            static fn (SettingDefinition $definition): array => [
                'key' => $definition->key,
                'description' => $definition->description,
                'type' => $definition->type->value,
            ],
            $settings->all(),
        ));

        return Inertia::render('Settings/Index', [
            'definitions' => $definitions,
        ]);
    }
}
