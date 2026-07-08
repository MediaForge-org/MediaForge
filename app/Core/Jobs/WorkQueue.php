<?php

declare(strict_types=1);

namespace App\Core\Jobs;

/**
 * The queues a job may run on (architecture/overview.md). Jobs declare their
 * queue via this enum, never a string literal.
 */
enum WorkQueue: string
{
    case Default = 'default';
    case Scan = 'scan';
    case Analyze = 'analyze';
    case Assemble = 'assemble';
    case Ai = 'ai';
    case Connector = 'connector';
}
