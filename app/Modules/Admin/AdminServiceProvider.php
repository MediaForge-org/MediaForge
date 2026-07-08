<?php

declare(strict_types=1);

namespace App\Modules\Admin;

use Illuminate\Support\ServiceProvider;

/**
 * The Admin module owns the dashboard, settings, health and backup surfaces of
 * the foundation. Routes, dashboard-card registrations and scheduled health
 * checks are wired here across the M7/M8 milestones.
 */
final class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
