<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

/**
 * Input to SaveConnectorConfig. A null/blank `secret` means "keep the stored
 * one"; `clearSecret` explicitly removes it. This split is what prevents an
 * edit-and-save from silently wiping existing credentials.
 */
final readonly class ConnectorConfigInput
{
    public function __construct(
        public string $key,
        public string $baseUrl,
        public ?string $secret = null,
        public bool $clearSecret = false,
    ) {}
}
