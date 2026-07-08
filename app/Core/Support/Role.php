<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Global user role (architecture/security.md). Roles are deliberately coarse;
 * fine-grained authorization is handled by Policies built on top.
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Manager => 'Manager',
            self::Member => 'Member',
        };
    }

    /** admin ⊇ manager ⊇ member — does this role satisfy the required one? */
    public function atLeast(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    private function rank(): int
    {
        return match ($this) {
            self::Admin => 3,
            self::Manager => 2,
            self::Member => 1,
        };
    }
}
