<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Actions;

use App\Connectors\Sdk\Models\ConnectorInstance;
use App\Connectors\Sdk\Models\ConnectorLibrary;
use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;
use App\Core\Audit\AuditRecorder;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Marks a discovered library as enabled (selected for a LATER sync) or not. This
 * only flips a boolean and audits it — it never triggers any media sync in V1 D.
 */
final class UpdateConnectorLibrarySelection extends AuditableAction
{
    public function __construct(
        AuditRecorder $audit,
        DatabaseManager $db,
    ) {
        parent::__construct($audit, $db);
    }

    public function execute(string $key, string $libraryId, bool $enabled): ConnectorLibrary
    {
        $instance = ConnectorInstance::query()->where('connector_key', $key)->first();

        if ($instance === null) {
            throw new ModelNotFoundException("Connector {$key} is not configured.");
        }

        $library = ConnectorLibrary::query()
            ->where('connector_instance_id', $instance->id)
            ->where('id', $libraryId)
            ->firstOrFail();

        $previous = $library->is_enabled;
        $library->is_enabled = $enabled;

        $this->transact(
            $library,
            new AuditChange(
                'connector.library_selection_changed',
                ['is_enabled' => ['old' => $previous, 'new' => $enabled]],
                ['connector' => $key, 'library' => $library->external_id],
            ),
            fn (): bool => $library->save(),
        );

        return $library;
    }
}
