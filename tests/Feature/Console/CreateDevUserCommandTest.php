<?php

declare(strict_types=1);

use App\Core\Support\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the dev user command creates and updates the local development user', function () {
    $this->artisan('mediaforge:dev-user')
        ->expectsOutput('Development user ready: test@mediaforge.local')
        ->assertExitCode(0);

    $user = User::query()->where('email', 'test@mediaforge.local')->firstOrFail();

    expect($user->name)->toBe('MediaForge Test User')
        ->and($user->role)->toBe(Role::Member)
        ->and($user->theme_preference)->toBe('system')
        ->and(Hash::check('test123456', $user->password_hash))->toBeTrue();

    $user->update(['name' => 'Changed User']);

    $this->artisan('mediaforge:dev-user')->assertExitCode(0);

    expect($user->fresh()->name)->toBe('MediaForge Test User');
});
