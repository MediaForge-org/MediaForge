<?php

declare(strict_types=1);

use App\Core\Artifacts\Artifact;
use App\Core\Artifacts\RegisterArtifact;
use App\Core\Artifacts\RegisterArtifactInput;
use App\Core\Review\CreateReviewTask;
use App\Core\Review\CreateReviewTaskInput;
use App\Core\Review\ReviewTask;
use Illuminate\Support\Str;

it('deduplicates an open review task for the same subject and type', function () {
    $input = new CreateReviewTaskInput('media_match', 'media_item', (string) Str::ulid(), 'job:Test');

    $first = app(CreateReviewTask::class)->execute($input);
    $second = app(CreateReviewTask::class)->execute($input);

    expect($second->id)->toBe($first->id)
        ->and(ReviewTask::query()->count())->toBe(1);
});

it('audits a newly created review task', function () {
    assertActionIsAudited('review.created', function () {
        app(CreateReviewTask::class)->execute(
            new CreateReviewTaskInput('media_match', 'media_item', (string) Str::ulid(), 'job:Test')
        );
    });
});

it('is idempotent when registering an artifact with the same signature', function () {
    $input = new RegisterArtifactInput(
        artifactType: 'other',
        sourceType: 'backup',
        sourceId: (string) Str::ulid(),
        generator: 'backup',
        generatorVersion: '1.0',
        inputSignature: 'sig-abc',
        path: '/backup/x.dump',
        sizeBytes: 100,
        checksum: 'blake3:deadbeef',
    );

    $first = app(RegisterArtifact::class)->execute($input);
    $second = app(RegisterArtifact::class)->execute($input);

    expect($second->id)->toBe($first->id)
        ->and(Artifact::query()->count())->toBe(1);
});
