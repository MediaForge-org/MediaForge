<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| PHPUnit bootstrap — pin the hermetic test environment
|--------------------------------------------------------------------------
| The dev container injects .env (APP_ENV=local, DB_DATABASE=mediaforge, redis)
| as REAL process env vars. PHPUnit's <env> entries do not reliably override real
| process env vars, so without this the suite runs as `local` against the dev
| database: Laravel then enforces CSRF (POST feature tests fail with 419) and
| RefreshDatabase would touch the dev DB.
|
| We set the test environment at the process level BEFORE Laravel boots. Laravel's
| Dotenv repository is immutable, so it will NOT overwrite these; the real .env
| still supplies everything else (APP_KEY, DB credentials, host, …). This is
| invocation-independent: `php artisan test`, `vendor/bin/pest`, the IDE and CI all
| honour it. It changes nothing in the running app — CSRF stays enabled on real
| web routes; Laravel simply skips it for the `testing` environment, as designed.
|
| On CI (no container env shadowing) these assignments match phpunit.xml and are
| harmless no-ops.
*/

$mediaforgeTestEnv = [
    'APP_ENV' => 'testing',
    'DB_DATABASE' => 'mediaforge_test',
    'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'BCRYPT_ROUNDS' => '4',
    'MAIL_MAILER' => 'array',
];

foreach ($mediaforgeTestEnv as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
