<?php

declare(strict_types=1);

namespace App\Core\Settings;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\Actor;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;

/**
 * The single write-point for runtime settings. Type-checks the value against the
 * setting's definition, persists only the override, and audits the change.
 */
final class UpdateSetting extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SettingsRegistry $registry,
        private readonly SettingsRepository $settings,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(UpdateSettingInput $input): Setting
    {
        $definition = $this->registry->definition($input->key);

        if ($definition === null) {
            throw new InvalidSettingException("Unknown setting: {$input->key}");
        }

        if (!$definition->type->accepts($input->value)) {
            throw new InvalidSettingException(
                "Setting {$input->key} expects {$definition->type->value}."
            );
        }

        $previous = $this->settings->get($input->key);

        $actor = Actor::current();
        $updatedBy = $actor->type === 'user' ? $actor->id : null;

        $subject = new Setting;
        $subject->key = $input->key;

        return $this->transact(
            $subject,
            new AuditChange('setting.updated', [
                $input->key => ['old' => $previous, 'new' => $input->value],
            ]),
            fn (): Setting => Setting::query()->updateOrCreate(
                ['key' => $input->key],
                ['value' => $input->value, 'updated_by' => $updatedBy, 'updated_at' => now()],
            ),
        );
    }
}
