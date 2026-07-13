<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * Outcome of a connection test. `detail` is a human-readable, secret-free message
 * safe to store and show. `serverName`/`serverVersion` are optional identity hints
 * detected from a successful response.
 */
final readonly class TestConnectionResult
{
    public function __construct(
        public ConnectorHealth $health,
        public string $detail,
        public ?int $httpStatus = null,
        public ?string $serverName = null,
        public ?string $serverVersion = null,
    ) {}

    public static function healthy(string $detail, ?int $httpStatus = null, ?string $serverName = null, ?string $serverVersion = null): self
    {
        return new self(ConnectorHealth::Healthy, $detail, $httpStatus, $serverName, $serverVersion);
    }

    public static function authFailed(string $detail, ?int $httpStatus = null): self
    {
        return new self(ConnectorHealth::AuthFailed, $detail, $httpStatus);
    }

    public static function unreachable(string $detail): self
    {
        return new self(ConnectorHealth::Unreachable, $detail);
    }

    public static function degraded(string $detail, ?int $httpStatus = null): self
    {
        return new self(ConnectorHealth::Degraded, $detail, $httpStatus);
    }
}
