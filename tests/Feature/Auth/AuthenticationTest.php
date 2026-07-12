<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withSession(['_token' => 'test-csrf-token']);
    $this->withHeader('X-CSRF-TOKEN', 'test-csrf-token');
});

test('guests can view the login page', function () {
    $this->withoutVite();

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('users can authenticate with valid credentials', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('users are redirected to their intended dashboard after login', function () {
    $user = User::factory()->create();

    $this->get('/dashboard')->assertRedirect('/login');

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('users cannot authenticate with invalid credentials', function () {
    $user = User::factory()->create();

    $this->from('/login')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'incorrect-password',
        ])
        ->assertRedirect('/login')
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('guests are redirected from dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users are redirected away from login', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/dashboard');
});

test('authenticated users can logout', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
});
