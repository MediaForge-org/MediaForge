<?php

declare(strict_types=1);

namespace App\Core\Audit;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * The cause of a business write, resolved contextually (architecture/overview.md
 * Rule 6). In an HTTP request it is the logged-in user; inside a queue job or a
 * connector sync it is the job/connector identity; otherwise the system.
 */
final readonly class Actor
{
    public function __construct(
        public string $type,   // user | job | connector | ai | system
        public ?string $id,
        public string $label,
    ) {}

    public static function user(User $user): self
    {
        return new self('user', $user->id, "user:{$user->id}");
    }

    public static function job(string $jobClass): self
    {
        return new self('job', null, "job:{$jobClass}");
    }

    public static function connector(string $key, string $instanceId): self
    {
        return new self('connector', $instanceId, "connector:{$key}:{$instanceId}");
    }

    public static function system(): self
    {
        return new self('system', null, 'system');
    }

    /**
     * Resolve the current actor: an explicit override (set by job/connector
     * middleware) wins, then the authenticated user, else the system.
     */
    public static function current(): self
    {
        $override = ActorContext::get();

        if ($override !== null) {
            return $override;
        }

        $user = Auth::user();

        return $user instanceof User ? self::user($user) : self::system();
    }

    /**
     * Run $callback with $actor as the current actor (used by jobs/connectors).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function runAs(self $actor, Closure $callback): mixed
    {
        $previous = ActorContext::get();
        ActorContext::set($actor);

        try {
            return $callback();
        } finally {
            ActorContext::set($previous);
        }
    }
}
