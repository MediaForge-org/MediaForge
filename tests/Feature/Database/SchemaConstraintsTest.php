<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| The database constraints are part of the contract (docs/MediaForge/database
| /core-schema.md). These tests target a real PostgreSQL database — a deliberate
| violation must fail.
*/

it('rejects an invalid user role (CHECK)', function () {
    expect(fn () => DB::table('users')->insert([
        'id' => (string) Str::ulid(),
        'name' => 'X',
        'email' => 'x@example.test',
        'password_hash' => 'x',
        'role' => 'superadmin',
    ]))->toThrow(QueryException::class);
});

it('rejects an invalid connector health_status (CHECK)', function () {
    expect(fn () => DB::table('connector_instances')->insert([
        'id' => (string) Str::ulid(),
        'connector_key' => 'jellyfin',
        'name' => 'x',
        'base_url' => 'http://x',
        'secrets_ref' => 'ref',
        'health_status' => 'on_fire',
    ]))->toThrow(QueryException::class);
});

it('enforces at most one primary edition per media item (partial unique index)', function () {
    $itemId = (string) Str::ulid();
    DB::table('media_items')->insert([
        'id' => $itemId, 'media_type' => 'movie', 'title' => 'Demo',
    ]);

    $edition = fn (bool $primary) => [
        'id' => (string) Str::ulid(),
        'media_item_id' => $itemId,
        'name' => 'default',
        'edition_kind' => 'release',
        'is_primary' => $primary,
    ];

    DB::table('media_editions')->insert($edition(true));

    expect(fn () => DB::table('media_editions')->insert($edition(true)))
        ->toThrow(QueryException::class);
});

it('forbids a duplicate open review for the same subject and type (partial unique index)', function () {
    $subjectId = (string) Str::ulid();
    $row = fn () => [
        'id' => (string) Str::ulid(),
        'task_type' => 'media_match',
        'subject_type' => 'media_item',
        'subject_id' => $subjectId,
        'status' => 'open',
        'created_by' => 'job:Test',
    ];

    DB::table('review_tasks')->insert($row());

    expect(fn () => DB::table('review_tasks')->insert($row()))
        ->toThrow(QueryException::class);
});

it('enforces one provider mapping per entity and provider (unique index)', function () {
    $entityId = (string) Str::ulid();
    $externalId = (string) Str::ulid();
    $row = fn () => [
        'id' => (string) Str::ulid(),
        'entity_type' => 'media_item',
        'entity_id' => $entityId,
        'provider' => 'jellyfin_item',
        'external_id' => $externalId,
        'source' => 'connector',
    ];

    DB::table('provider_ids')->insert($row());

    expect(fn () => DB::table('provider_ids')->insert($row()))
        ->toThrow(QueryException::class);
});
