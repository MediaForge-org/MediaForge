<?php

declare(strict_types=1);

namespace App\Core\Review;

final readonly class CreateReviewTaskInput
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public string $taskType,
        public string $subjectType,
        public string $subjectId,
        public string $createdBy,
        public string $priority = 'normal',
        public array $evidence = [],
    ) {}
}
