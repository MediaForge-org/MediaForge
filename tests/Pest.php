<?php

declare(strict_types=1);

use App\Connectors\Sdk\Actions\NormalizeConnectorCatalogItems;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Audit\AuditLog;
use App\Core\Jobs\CheckpointStore;
use App\Core\Jobs\ResumableJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
    'Feature/Sync',
    'Feature/Review',
    'Feature/Catalog',
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

/*
|--------------------------------------------------------------------------
| Catalog normalization harness (V2 C)
|--------------------------------------------------------------------------
| Shared by the catalog normalization + match preview suites. Everything is
| built directly in the database — no HTTP, no snapshot, no network.
*/

/**
 * A configured connector with one discovered library.
 *
 * @return array{0: ConnectorInstance, 1: ConnectorLibrary}
 */
function seedNormalizationConnector(string $key = 'jellyfin', string $token = 'NORM-TOKEN'): array
{
    $ref = (string) Str::ulid();
    app(SecretStore::class)->put($ref, $token);

    $instance = ConnectorInstance::query()->create([
        'connector_key' => $key,
        'name' => ucfirst($key),
        'base_url' => 'http://'.$key.'.local:8096',
        'secrets_ref' => $ref,
        'health_status' => 'healthy',
        'libraries_discovered_at' => now(),
    ]);

    $library = ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $key,
        'external_id' => $key.'-lib',
        'name' => 'Movies',
        'collection_type' => 'movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);

    return [$instance, $library];
}

/**
 * Capture one external item with full control over the reported fields.
 *
 * @param  array<string, mixed>  $extra
 */
function seedNormalizationItem(
    ConnectorInstance $instance,
    ConnectorLibrary $library,
    string $externalId,
    string $title,
    string $kind = 'movie',
    array $extra = [],
): ConnectorCatalogItem {
    return ConnectorCatalogItem::query()->create(array_merge([
        'connector_instance_id' => $instance->id,
        'connector_library_id' => $library->id,
        'external_id' => $externalId,
        'media_kind' => $kind,
        'title' => $title,
        'year' => 1999,
        'runtime_seconds' => 7200,
        'first_seen_at' => now(),
        'last_seen_at' => now(),
        'is_present' => true,
        'metadata' => [],
    ], $extra));
}

/**
 * Run the normalization action over a connector, optionally one library.
 *
 * @return array<string, int>
 */
function normalizeConnector(ConnectorInstance $instance, string $key = 'jellyfin', ?ConnectorLibrary $library = null): array
{
    return app(NormalizeConnectorCatalogItems::class)->execute($instance, $key, $library);
}
