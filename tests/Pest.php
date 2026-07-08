<?php

declare(strict_types=1);

use App\Core\Audit\AuditLog;
use App\Core\Jobs\CheckpointStore;
use App\Core\Jobs\ResumableJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case bindings
|--------------------------------------------------------------------------
| The base TestCase is assigned once to Feature + Unit. RefreshDatabase is an
| additive trait applied only to the suites that touch the database.
*/

uses(TestCase::class)->in('Feature', 'Unit');

uses(RefreshDatabase::class)->in(
    'Feature/Core',
    'Feature/Database',
    'Feature/Connectors',
    'Feature/Auth',
    'Feature/Admin',
);

/*
|--------------------------------------------------------------------------
| Shared harnesses (developer-handbook/testing.md)
|--------------------------------------------------------------------------
*/

/**
 * Assert that running $callback records exactly one audit entry with $action.
 *
 * @param  Closure(): void  $callback
 */
function assertActionIsAudited(string $action, Closure $callback): void
{
    $before = AuditLog::query()->count();

    $callback();

    expect(AuditLog::query()->count())->toBe($before + 1)
        ->and(AuditLog::query()->latest('created_at')->first()?->action)->toBe($action);
}

/** Run a ResumableJob twice and assert the second run performs no further work. */
function assertJobIsIdempotent(ResumableJob $job, Closure $sideEffectCount): void
{
    $store = app(CheckpointStore::class);

    $job->handle($store);
    $afterFirst = $sideEffectCount();

    $job->handle($store);
    $afterSecond = $sideEffectCount();

    expect($afterSecond)->toBe($afterFirst);
}
