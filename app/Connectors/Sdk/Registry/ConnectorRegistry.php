<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Registry;

use App\Connectors\Sdk\Contracts\ConnectorProvider;
use InvalidArgumentException;

/**
 * Central lookup of the connector types available in this build. Concrete
 * connectors register themselves here from their service providers; the HTTP
 * layer and SDK actions resolve providers by key without knowing the classes.
 */
final class ConnectorRegistry
{
    /** @var array<string, ConnectorProvider> */
    private array $providers = [];

    public function register(ConnectorProvider $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): ConnectorProvider
    {
        return $this->providers[$key]
            ?? throw new InvalidArgumentException("Unknown connector: {$key}");
    }

    /** @return list<ConnectorProvider> Registration order is preserved for display. */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->providers);
    }
}
