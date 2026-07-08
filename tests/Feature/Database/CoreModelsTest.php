<?php

declare(strict_types=1);

use App\Core\Media\Library;
use App\Core\Settings\Setting;
use App\Core\Support\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('generates a ULID id and hashes the password on the user model', function () {
    $user = User::factory()->admin()->create();

    expect(strlen($user->id))->toBe(26)
        ->and($user->role)->toBe(Role::Admin)
        ->and($user->isAdmin())->toBeTrue()
        ->and(Hash::check('password', $user->password_hash))->toBeTrue()
        ->and($user->password_hash)->not->toBe('password');
});

it('casts the user role to the Role enum and applies the hierarchy', function () {
    $manager = User::factory()->manager()->create();

    expect($manager->role)->toBe(Role::Manager)
        ->and($manager->hasRole(Role::Member))->toBeTrue()
        ->and($manager->hasRole(Role::Admin))->toBeFalse();
});

it('round-trips a JSON settings value', function () {
    Setting::query()->create([
        'key' => 'demo.example.value',
        'value' => ['enabled' => true, 'count' => 3],
    ]);

    $setting = Setting::query()->find('demo.example.value');

    // JSONB does not preserve key order, so compare by equality, not identity.
    expect($setting->value)->toEqual(['enabled' => true, 'count' => 3]);
});

it('creates a library with a ULID and boolean cast', function () {
    $library = Library::query()->create([
        'name' => 'Audiobooks',
        'root_path' => '/media/audiobooks',
        'media_kind' => 'audiobook',
    ]);
    $library->refresh(); // load DB defaults (scan_enabled)

    expect(strlen($library->id))->toBe(26)
        ->and($library->scan_enabled)->toBeTrue();
});
