<?php

declare(strict_types=1);

namespace App\Core\Audit;

/**
 * Mutable holder for an explicitly-set current Actor (used by job/connector
 * middleware). Kept separate from the immutable Actor value object.
 */
final class ActorContext
{
    private static ?Actor $current = null;

    public static function get(): ?Actor
    {
        return self::$current;
    }

    public static function set(?Actor $actor): void
    {
        self::$current = $actor;
    }
}
