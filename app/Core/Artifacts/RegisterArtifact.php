<?php

declare(strict_types=1);

namespace App\Core\Artifacts;

use App\Core\Actions\AuditableAction;
use App\Core\Audit\AuditChange;

/**
 * Registers a generated file. Idempotent via the (generator, input_signature)
 * anchor: an active/building artifact with the same signature is returned as-is
 * instead of creating a duplicate.
 */
final class RegisterArtifact extends AuditableAction
{
    public function execute(RegisterArtifactInput $input): Artifact
    {
        $existing = Artifact::query()
            ->where('generator', $input->generator)
            ->where('input_signature', $input->inputSignature)
            ->whereIn('status', ['building', 'active'])
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $artifact = new Artifact([
            'artifact_type' => $input->artifactType,
            'source_type' => $input->sourceType,
            'source_id' => $input->sourceId,
            'generator' => $input->generator,
            'generator_version' => $input->generatorVersion,
            'input_signature' => $input->inputSignature,
            'params' => $input->params,
            'path' => $input->path,
            'size_bytes' => $input->sizeBytes,
            'checksum' => $input->checksum,
        ]);
        $artifact->status = 'active';

        return $this->transact(
            $artifact,
            new AuditChange('artifact.registered', [
                'artifact_type' => $input->artifactType,
                'generator' => $input->generator,
                'path' => $input->path,
            ]),
            function () use ($artifact): Artifact {
                $artifact->save();

                return $artifact;
            },
        );
    }
}
