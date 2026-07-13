<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Connectors\ConnectorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'version' => 'v1-foundation',
]))->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    $connectors = ['jellyfin', 'audiobookshelf'];

    Route::get('/connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    Route::get('/connectors/{connector}', [ConnectorController::class, 'show'])
        ->whereIn('connector', $connectors)->name('connectors.show');
    Route::post('/connectors/{connector}', [ConnectorController::class, 'update'])
        ->whereIn('connector', $connectors)->name('connectors.update');
    Route::post('/connectors/{connector}/test', [ConnectorController::class, 'test'])
        ->whereIn('connector', $connectors)->name('connectors.test');
});
