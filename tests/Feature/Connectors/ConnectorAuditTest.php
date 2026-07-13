<?php

declare(strict_types=1);

use App\Core\Audit\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
});

test('saving a connector configuration records a sanitized audit entry', function () {
    $user = User::factory()->create();

    assertActionIsAudited('connector.configured', function () use ($user) {
        $this->actingAs($user)->post('/connectors/jellyfin', [
            'base_url' => 'http://jellyfin.local:8096',
            'secret' => 'CONFIG-TOKEN',
        ])->assertRedirect('/connectors/jellyfin');
    });

    $entry = AuditLog::query()->latest('created_at')->firstOrFail();

    expect($entry->changes)->toHaveKey('base_url')
        ->and(json_encode($entry->changes))->toContain('jellyfin.local');
});

test('running a connection test records a sanitized audit entry', function () {
    Http::fake(['*/System/Info' => Http::response(['ServerName' => 'JF', 'Version' => '10.9'], 200)]);

    $user = User::factory()->create();

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'CONFIG-TOKEN',
    ]);

    // Assert the specific action directly: under RefreshDatabase every now() in a
    // test shares one transaction timestamp, so latest('created_at') can't reliably
    // distinguish the config vs test audit rows.
    $this->actingAs($user)->post('/connectors/jellyfin/test');

    $entry = AuditLog::query()->where('action', 'connector.tested')->sole();

    expect($entry->context)->toHaveKey('connector')
        ->and($entry->context['connector'])->toBe('jellyfin');
});

test('the audit log never stores a raw API token', function () {
    Http::fake(['*/System/Info' => Http::response(['ServerName' => 'JF', 'Version' => '10.9'], 200)]);

    $user = User::factory()->create();

    $this->actingAs($user)->post('/connectors/jellyfin', [
        'base_url' => 'http://jellyfin.local:8096',
        'secret' => 'RAW-AUDIT-TOKEN',
    ]);
    $this->actingAs($user)->post('/connectors/jellyfin/test');

    $serialized = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->changes).json_encode($log->context))
        ->implode('');

    expect($serialized)->not->toContain('RAW-AUDIT-TOKEN');
});
