<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Secrets;

/**
 * Encrypted, reference-keyed store for connector credentials. The plaintext
 * secret is written here and referenced from connector_instances.secrets_ref;
 * it is never persisted anywhere else and never returned to the frontend.
 */
interface SecretStore
{
    public function put(string $ref, string $plaintext): void;

    public function get(string $ref): ?string;

    public function has(string $ref): bool;

    public function forget(string $ref): void;
}
