<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Agent => 'Agent',
        };
    }

    /**
     * The roles a user with this role may give to other users.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::SuperAdmin => self::cases(),
            self::Admin => [self::Agent],
            self::Agent => [],
        };
    }
}
