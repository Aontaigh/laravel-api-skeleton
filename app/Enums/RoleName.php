<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Spatie role names stored in Title Case.
 */
enum RoleName: string
{
    case Admin = 'Admin';
    case Manager = 'Manager';
    case User = 'User';
    case Service = 'Service';
}
