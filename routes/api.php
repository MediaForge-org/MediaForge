<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (/api/v1)
|--------------------------------------------------------------------------
| External REST surface (Sanctum bearer tokens with capability scopes). The
| web UI itself uses Inertia, not this API. Endpoints are added per milestone;
| see docs/MediaForge/api/conventions.md.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    // Connector management + diagnostics are registered in M6/M7.
});
