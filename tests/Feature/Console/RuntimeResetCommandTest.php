<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Point public_path() at a writable temp directory so the test never depends on
 * the real public/ mount (which is read-only for the container user on Windows
 * bind mounts).
 */
function useTempPublicPath(): string
{
    $dir = sys_get_temp_dir().'/mf-public-'.uniqid();
    File::ensureDirectoryExists($dir);
    app()->usePublicPath($dir);

    return $dir;
}

test('the runtime reset command removes public/hot and clears caches', function () {
    $dir = useTempPublicPath();
    File::put($dir.'/hot', 'http://localhost:5273');

    $this->artisan('mediaforge:runtime:reset')
        ->expectsOutputToContain('Removed public/hot')
        ->expectsOutputToContain('Runtime reset to production-build mode.')
        ->assertExitCode(0);

    expect(File::exists($dir.'/hot'))->toBeFalse();

    File::deleteDirectory($dir);
});

test('the runtime reset command succeeds when public/hot is already absent', function () {
    $dir = useTempPublicPath();

    $this->artisan('mediaforge:runtime:reset')
        ->expectsOutputToContain('public/hot already absent')
        ->assertExitCode(0);

    File::deleteDirectory($dir);
});

test('the runtime reset command is restricted to local and testing environments', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('mediaforge:runtime:reset')
        ->expectsOutputToContain('available only in local or testing environments')
        ->assertExitCode(1);
});
