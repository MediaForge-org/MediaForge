<?php

declare(strict_types=1);

use App\Core\Jobs\CheckpointStore;
use App\Core\Jobs\JobStep;
use App\Core\Jobs\ResumableJob;
use App\Core\Settings\Setting;

it('produces the same end state when run twice (idempotent steps)', function () {
    $job = new class('job:markers') extends ResumableJob
    {
        public function __construct(private string $key) {}

        public function checkpointKey(): string
        {
            return $this->key;
        }

        public function steps(): array
        {
            return [
                new JobStep('a', fn () => Setting::query()->updateOrCreate(['key' => 'marker.a'], ['value' => true])),
                new JobStep('b', fn () => Setting::query()->updateOrCreate(['key' => 'marker.b'], ['value' => true])),
            ];
        }
    };

    assertJobIsIdempotent($job, fn () => Setting::query()->where('key', 'like', 'marker.%')->count());

    expect(Setting::query()->where('key', 'like', 'marker.%')->count())->toBe(2);
});

it('skips steps already recorded as completed on resume', function () {
    $store = app(CheckpointStore::class);
    $store->markCompleted('job:resume', 'a'); // pretend step a finished before the crash

    $job = new class('job:resume') extends ResumableJob
    {
        public function __construct(private string $key) {}

        public function checkpointKey(): string
        {
            return $this->key;
        }

        public function steps(): array
        {
            return [
                new JobStep('a', fn () => throw new RuntimeException('step a must be skipped')),
                new JobStep('b', fn () => Setting::query()->updateOrCreate(['key' => 'marker.b'], ['value' => true])),
            ];
        }
    };

    $job->handle($store);

    expect(Setting::query()->find('marker.b'))->not->toBeNull();
});
