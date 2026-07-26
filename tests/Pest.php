<?php

declare(strict_types=1);

use App\Connectors\Sdk\Actions\CreateMediaImportPlan;
use App\Connectors\Sdk\Actions\NormalizeConnectorCatalogItems;
use App\Connectors\Sdk\Import\ImportPlanScope;
use App\Connectors\Sdk\Models\ConnectorCatalogItem;
use App\Connectors\Sdk\Models\ConnectorCatalogItemNormalization;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Connectors\Sdk\Models\MediaImportPlan;
use App\Connectors\Sdk\Models\MediaImportPlanItem;
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
    'Feature/Imports',
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

/** A second discovered library on an existing connector instance. */
function seedNormalizationLibrary(ConnectorInstance $instance, string $externalId, string $name): ConnectorLibrary
{
    return ConnectorLibrary::query()->create([
        'connector_instance_id' => $instance->id,
        'provider_key' => $instance->connector_key,
        'external_id' => $externalId,
        'name' => $name,
        'collection_type' => 'movies',
        'is_enabled' => true,
        'discovery_status' => 'present',
        'last_seen_at' => now(),
    ]);
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

/*
|--------------------------------------------------------------------------
| Import plan harness (V2 D)
|--------------------------------------------------------------------------
| Builds a dry run straight through the action — no HTTP, no network. Creating
| a plan never imports media and never touches a file.
*/

/** Run the import dry run for a scope and return the stored plan. */
function createImportPlan(
    ImportPlanScope $scope = ImportPlanScope::All,
    ?ConnectorInstance $instance = null,
    ?ConnectorLibrary $library = null,
): MediaImportPlan {
    return app(CreateMediaImportPlan::class)->execute($scope, $instance, $library);
}

/**
 * Capture and normalize $count items in two bulk inserts. Used only where the
 * point of the test is a BOUND (the plan cap), not the seeding path itself —
 * building thousands of rows one model at a time would make the suite crawl.
 */
function seedBulkNormalizedItems(ConnectorInstance $instance, ConnectorLibrary $library, int $count): void
{
    $now = now();
    $items = [];
    $normalizations = [];

    for ($i = 0; $i < $count; $i++) {
        $itemId = (string) Str::ulid();
        $title = sprintf('Bulk Item %05d', $i);

        $items[] = [
            'id' => $itemId,
            'connector_instance_id' => $instance->id,
            'connector_library_id' => $library->id,
            'external_id' => "bulk-{$i}",
            'media_kind' => 'movie',
            'title' => $title,
            'year' => 1999,
            'runtime_seconds' => 7200,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
            'is_present' => true,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $normalizations[] = [
            'id' => (string) Str::ulid(),
            'connector_catalog_item_id' => $itemId,
            'connector_instance_id' => $instance->id,
            'connector_library_id' => $library->id,
            'normalized_kind' => 'movie',
            'normalized_title' => $title,
            'normalized_sort_title' => strtolower($title),
            'release_year' => 1999,
            'runtime_seconds' => 7200,
            'confidence' => 100,
            'status' => 'clean',
            'issues' => '[]',
            'normalized_data' => '{}',
            'normalized_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    foreach (array_chunk($items, 500) as $chunk) {
        ConnectorCatalogItem::query()->insert($chunk);
    }

    foreach (array_chunk($normalizations, 500) as $chunk) {
        ConnectorCatalogItemNormalization::query()->insert($chunk);
    }
}

/** The planned line for one captured external item, or null when absent. */
function planItemFor(MediaImportPlan $plan, string $externalId): ?MediaImportPlanItem
{
    return MediaImportPlanItem::query()
        ->where('media_import_plan_id', $plan->id)
        ->whereHas('catalogItem', fn ($query) => $query->where('external_id', $externalId))
        ->first();
}
