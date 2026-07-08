<?php

declare(strict_types=1);

use App\Core\Settings\InvalidSettingException;
use App\Core\Settings\Setting;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\UpdateSetting;
use App\Core\Settings\UpdateSettingInput;

it('returns the code default when no override exists', function () {
    expect(app(SettingsRepository::class)->get('api.token_expiry_days'))->toBe(365);
});

it('persists an override through UpdateSetting and audits it', function () {
    assertActionIsAudited('setting.updated', function () {
        app(UpdateSetting::class)->execute(new UpdateSettingInput('api.token_expiry_days', 30));
    });

    expect(Setting::query()->find('api.token_expiry_days')->value)->toBe(30)
        ->and(app(SettingsRepository::class)->get('api.token_expiry_days'))->toBe(30);
});

it('rejects an unknown setting key', function () {
    app(UpdateSetting::class)->execute(new UpdateSettingInput('nope.does.not.exist', 1));
})->throws(InvalidSettingException::class);

it('rejects a value of the wrong type', function () {
    app(UpdateSetting::class)->execute(new UpdateSettingInput('api.token_expiry_days', 'not-an-int'));
})->throws(InvalidSettingException::class);

it('merges defaults with overrides in all()', function () {
    app(UpdateSetting::class)->execute(new UpdateSettingInput('api.rate_limit_write', 120));

    $all = app(SettingsRepository::class)->all();

    expect($all['api.rate_limit_write'])->toBe(120)
        ->and($all['api.rate_limit_standard'])->toBe(300); // untouched default
});
