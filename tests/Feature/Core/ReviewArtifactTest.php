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

it('reuses the review task a concurrent request just won instead of crashing', function () {
    // A double-submitted button, a retried request, or two open tabs can both pass
    // the "no open task yet" check before either commits. Model a TRUE race: a
    // second, independent database connection (not a savepoint of this test's own
    // transaction, which the eventual violation would roll back) wins the insert
    // in the instant between this call's own check and its own insert, via the
    // `saving` hook that fires right before that insert — exactly what a second
    // HTTP worker handling a duplicate request would do.
    $input = new CreateReviewTaskInput('media_match', 'media_item', (string) Str::ulid(), 'job:Test');
    $racingId = (string) Str::ulid();

    // A raw, independent PDO connection: this insert commits for real, on its own
    // connection, so this test's own (RefreshDatabase) transaction rolling back
    // later can never undo it — exactly what makes it a genuine race and not a
    // savepoint of the same transaction.
    $racingPdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            config('database.connections.pgsql.host'),
            config('database.connections.pgsql.port'),
            config('database.connections.pgsql.database'),
        ),
        config('database.connections.pgsql.username'),
        config('database.connections.pgsql.password'),
    );

    ReviewTask::saving(function () use ($input, $racingId, $racingPdo) {
        $statement = $racingPdo->prepare(<<<'SQL'
            INSERT INTO review_tasks (id, task_type, subject_type, subject_id, status, priority, evidence, created_by, created_at, updated_at)
            VALUES (:id, :task_type, :subject_type, :subject_id, 'open', :priority, '{}', :created_by, now(), now())
            SQL);
        $statement->execute([
            'id' => $racingId,
            'task_type' => $input->taskType,
            'subject_type' => $input->subjectType,
            'subject_id' => $input->subjectId,
            'priority' => $input->priority,
            'created_by' => $input->createdBy,
        ]);
    });

    try {
        $result = app(CreateReviewTask::class)->execute($input);

        expect($result->id)->toBe($racingId)
            ->and(ReviewTask::query()->count())->toBe(1);
    } finally {
        ReviewTask::flushEventListeners();
        // Committed on a separate connection — RefreshDatabase's rollback of THIS
        // test's transaction cannot remove it, so it must be cleaned up explicitly
        // to avoid leaking into other tests' review-task counts.
        $racingPdo->exec('DELETE FROM review_tasks WHERE id = '.$racingPdo->quote($racingId));
    }
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
