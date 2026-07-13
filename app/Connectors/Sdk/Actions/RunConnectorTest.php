<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Diagnostics\TestConnectionRequest;
use App\Connectors\Sdk\Diagnostics\TestConnectionResult;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use RuntimeException;

/**
 * Runs a connection test and records the outcome. The network probe happens
 * OUTSIDE the transaction (no I/O inside a DB transaction); only the health
 * snapshot and audit entry are written transactionally. The provider is
 * responsible for never leaking the secret into the sanitized result.
 */
final class RunConnectorTest extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly ConnectorRegistry $registry,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(string $key): TestConnectionResult
    {
        $provider = $this->registry->get($key);

        $instance = ConnectorInstance::query()->where('connector_key', $key)->first();

        if ($instance === null) {
            throw new RuntimeException("Connector {$key} is not configured.");
        }

        $result = $provider->testConnection(new TestConnectionRequest(
            $instance->base_url,
            $this->secrets->get($instance->secrets_ref),
        ));

        $previousHealth = $instance->health_status;

        $instance->health_status = $result->health->value;
        $instance->health_detail = $result->detail;
        $instance->last_checked_at = now();

        if ($result->health->isHealthy()) {
            $instance->last_healthy_at = now();
        }

        $this->transact(
            $instance,
            new AuditChange(
                'connector.tested',
                ['health' => ['old' => $previousHealth, 'new' => $result->health->value]],
                ['connector' => $key, 'http_status' => $result->httpStatus],
            ),
            fn (): bool => $instance->save(),
        );

        return $result;
    }
}
