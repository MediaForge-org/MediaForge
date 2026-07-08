<?php

declare(strict_types=1);
use App\Connectors\Audiobookshelf\AudiobookshelfConnectorServiceProvider;
use App\Connectors\Jellyfin\JellyfinConnectorServiceProvider;
use App\Connectors\Sdk\ConnectorSdkServiceProvider;
use App\Core\CoreServiceProvider;
use App\Modules\Admin\AdminServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    ConnectorSdkServiceProvider::class,
    JellyfinConnectorServiceProvider::class,
    AudiobookshelfConnectorServiceProvider::class,
    AdminServiceProvider::class,
];
