<?php

declare(strict_types=1);

namespace App\Core\Artifacts;

final readonly class RegisterArtifactInput
{
    /** @param array<string, mixed> $params */
    public function __construct(
        public string $artifactType,
        public string $sourceType,
        public string $sourceId,
        public string $generator,
        public string $generatorVersion,
        public string $inputSignature,
        public string $path,
        public int $sizeBytes,
        public string $checksum,
        public array $params = [],
    ) {}
}
