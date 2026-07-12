<?php

declare(strict_types=1);

use App\Core\Support\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withSession(['_token' => 'test-csrf-token']);
    $this->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
});

test('guests can view the register page', function () {
    $this->withoutVite();

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

test('users can register', function () {
    $this->post('/register', registrationData())
        ->assertRedirect('/dashboard')
        ->assertHeader('Location', '/dashboard');

    $user = User::query()->where('email', 'new.user@example.test')->firstOrFail();

    expect($user->name)->toBe('New User')
        ->and($user->role)->toBe(Role::Member)
        ->and(Hash::check('correct-password', $user->password_hash))->toBeTrue();
});

test('registered users are authenticated and redirected to dashboard', function () {
    $this->post('/register', registrationData())
        ->assertRedirect('/dashboard')
        ->assertHeader('Location', '/dashboard');

    $this->assertAuthenticated();
});

test('email must be unique', function () {
    User::factory()->create(['email' => 'new.user@example.test']);

    $this->from('/register')
        ->post('/register', registrationData())
        ->assertRedirect('/register')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('password confirmation is required', function () {
    $this->from('/register')
        ->post('/register', [
            ...registrationData(),
            'password_confirmation' => '',
        ])
        ->assertRedirect('/register')
        ->assertSessionHasErrors('password');

    $this->assertGuest();
});

/** @return array{name: string, email: string, password: string, password_confirmation: string} */
function registrationData(): array
{
    return [
        'name' => 'New User',
        'email' => 'new.user@example.test',
        'password' => 'correct-password',
        'password_confirmation' => 'correct-password',
    ];
}
