<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Diagnostics\ConnectorHealth;
use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Registry\ConnectorRegistry;
use App\Connectors\Sdk\Secrets\SecretStore;
use App\Connectors\Sdk\Support\BaseUrl;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The single write-point for connector configuration. Persists the non-secret
 * config on the instance, routes the credential to the encrypted secret store,
 * and audits the change atomically. The secret never enters the audit payload.
 */
final class SaveConnectorConfig extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
        private readonly SecretStore $secrets,
        private readonly ConnectorRegistry $registry,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(ConnectorConfigInput $input): ConnectorInstance
    {
        if (!$this->registry->has($input->key)) {
            throw new InvalidArgumentException("Unknown connector: {$input->key}");
        }

        $instance = ConnectorInstance::query()->firstOrNew(['connector_key' => $input->key]);
        $oldUrl = $instance->exists ? $instance->base_url : '';
        $newUrl = BaseUrl::normalize($input->baseUrl);

        if (!$instance->exists) {
            $instance->secrets_ref = (string) Str::ulid();
            $instance->name = $this->registry->get($input->key)->label();
            $instance->health_status = ConnectorHealth::Unknown->value;
        }

        $instance->base_url = $newUrl;

        return $this->transact(
            $instance,
            new AuditChange(
                'connector.configured',
                ['base_url' => ['old' => $oldUrl, 'new' => $newUrl]],
                ['connector' => $input->key],
            ),
            function () use ($instance, $input): ConnectorInstance {
                $instance->save();

                if ($input->clearSecret) {
                    $this->secrets->forget($instance->secrets_ref);
                } elseif ($input->secret !== null && trim($input->secret) !== '') {
                    $this->secrets->put($instance->secrets_ref, $input->secret);
                }

                return $instance;
            },
        );
    }
}
