<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * Health of a configured connector instance. The string values match the
 * connector_instances.health_status CHECK constraint. `Unknown` means "saved but
 * not tested yet"; the three failure states distinguish network vs auth vs other.
 */
enum ConnectorHealth: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Degraded = 'degraded';
    case Unreachable = 'unreachable';
    case AuthFailed = 'auth_failed';

    /**
     * Collapse to the three states the UI badges use plus `unknown`. "Not
     * configured" is represented by the absence of an instance, not here.
     */
    public function uiStatus(): string
    {
        return match ($this) {
            self::Healthy => 'healthy',
            self::Unknown => 'unknown',
            self::Degraded, self::Unreachable, self::AuthFailed => 'unhealthy',
        };
    }

    public function isHealthy(): bool
    {
        return $this === self::Healthy;
    }
}
