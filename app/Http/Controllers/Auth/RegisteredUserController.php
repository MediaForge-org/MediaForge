<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Core\Support\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('name')->toString(),
            'email' => Str::lower($request->string('email')->toString()),
            'password_hash' => Hash::make($request->string('password')->toString()),
            'role' => Role::Member,
            'theme_preference' => 'system',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return new RedirectResponse('/dashboard');
    }
}
