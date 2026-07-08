<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Module boundary architecture tests (architecture/overview.md)
|--------------------------------------------------------------------------
| These are part of the foundation. Allowed dependency directions:
|   Http -> Modules/Core, Modules -> Core, Connectors -> Sdk/Core, Sdk -> Core.
| They grow with every new module.
*/

arch('core has no dependencies on modules or connectors')
    ->expect('App\Core')
    ->not->toUse(['App\Modules', 'App\Connectors']);

arch('connectors never depend on modules')
    ->expect('App\Connectors')
    ->not->toUse('App\Modules');

arch('the connector sdk does not depend on concrete connectors or modules')
    ->expect('App\Connectors\Sdk')
    ->not->toUse(['App\Modules', 'App\Connectors\Jellyfin', 'App\Connectors\Audiobookshelf']);

arch('the jellyfin and audiobookshelf connectors do not depend on each other')
    ->expect('App\Connectors\Jellyfin')
    ->not->toUse('App\Connectors\Audiobookshelf');

arch('modules never depend on connectors')
    ->expect('App\Modules')
    ->not->toUse('App\Connectors');

arch('controllers contain no persistence')
    ->expect('App\Http\Controllers')
    ->not->toUse(DB::class);

arch('strict types are declared everywhere')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debug statements ship')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'ddd'])
    ->not->toBeUsed();
