<?php

declare(strict_types=1);

use App\Core\Audit\Actor;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditLog;
use App\Core\Audit\DatabaseAuditRecorder;
use App\Core\Settings\Setting;
use App\Core\Settings\UpdateSetting;
use App\Core\Settings\UpdateSettingInput;
use App\Models\User;

it('records the subject, action and system actor by default', function () {
    app(UpdateSetting::class)->execute(new UpdateSettingInput('api.rate_limit_write', 90));

    $entry = AuditLog::query()->latest('created_at')->first();

    expect($entry->action)->toBe('setting.updated')
        ->and($entry->subject_type)->toBe('setting')
        ->and($entry->subject_id)->toBe('api.rate_limit_write')
        ->and($entry->actor_type)->toBe('system');
});

it('attributes the change to the acting user', function () {
    $admin = User::factory()->admin()->create();

    Actor::runAs(Actor::user($admin), function () {
        app(UpdateSetting::class)->execute(new UpdateSettingInput('api.rate_limit_write', 90));
    });

    $entry = AuditLog::query()->latest('created_at')->first();

    expect($entry->actor_type)->toBe('user')
        ->and($entry->actor_id)->toBe($admin->id);
});

it('masks secret-looking values in the audit changes (denylist)', function () {
    // Drive the recorder directly with a change containing a secret-named field.
    $recorder = app(DatabaseAuditRecorder::class);
    $subject = new Setting;
    $subject->key = 'demo';

    $recorder->record(
        $subject,
        new AuditChange('demo.action', [
            'api_key' => 'super-secret-value',
            'nested' => ['password' => 'hunter2', 'safe' => 'visible'],
        ]),
        Actor::system(),
    );

    $entry = AuditLog::query()->latest('created_at')->first();

    expect($entry->changes['api_key'])->toBe('***')
        ->and($entry->changes['nested']['password'])->toBe('***')
        ->and($entry->changes['nested']['safe'])->toBe('visible');
});
