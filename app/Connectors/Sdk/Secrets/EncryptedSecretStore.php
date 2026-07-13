<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Secrets;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;

/**
 * Stores connector secrets as Laravel-encrypted strings (APP_KEY) in the
 * connector_secrets table. Decryption happens only in-memory when a connection
 * test needs to build an auth header; the plaintext is never logged or returned.
 */
final class EncryptedSecretStore implements SecretStore
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly Encrypter $encrypter,
    ) {}

    public function put(string $ref, string $plaintext): void
    {
        $this->db->table('connector_secrets')->updateOrInsert(
            ['secrets_ref' => $ref],
            ['ciphertext' => $this->encrypter->encryptString($plaintext), 'updated_at' => now()],
        );
    }

    public function get(string $ref): ?string
    {
        $ciphertext = $this->db->table('connector_secrets')
            ->where('secrets_ref', $ref)
            ->value('ciphertext');

        if (!is_string($ciphertext) || $ciphertext === '') {
            return null;
        }

        return $this->encrypter->decryptString($ciphertext);
    }

    public function has(string $ref): bool
    {
        return $this->db->table('connector_secrets')->where('secrets_ref', $ref)->exists();
    }

    public function forget(string $ref): void
    {
        $this->db->table('connector_secrets')->where('secrets_ref', $ref)->delete();
    }
}
