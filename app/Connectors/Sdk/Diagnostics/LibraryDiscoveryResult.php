<?php

declare(strict_types=1);

namespace App\Connectors\Sdk\Diagnostics;

/**
 * Outcome of a library discovery. `detail` is a human-readable, secret-free
 * message safe to store and show. On failure `libraries` is empty and the caller
 * MUST NOT wipe previously discovered libraries.
 */
final readonly class LibraryDiscoveryResult
{
    /** @param list<DiscoveredLibrary> $libraries */
    public function __construct(
        public bool $ok,
        public string $detail,
        public array $libraries = [],
        public ?int $httpStatus = null,
    ) {}

    /** @param list<DiscoveredLibrary> $libraries */
    public static function success(array $libraries, string $detail, ?int $httpStatus = null): self
    {
        return new self(true, $detail, $libraries, $httpStatus);
    }

    public static function failure(string $detail, ?int $httpStatus = null): self
    {
        return new self(false, $detail, [], $httpStatus);
    }
}
