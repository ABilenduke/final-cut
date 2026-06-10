<?php

namespace App\Exceptions;

use DomainException;

/**
 * Thrown by `AdminUserService` for invalid staff-management operations.
 * The self-guards exist for lockout safety (you cannot disable yourself)
 * and audit integrity (privilege changes on your own account need a second
 * pair of hands).
 */
class AdminUserException extends DomainException
{
    public const REASON_SELF_ROLE = 'self_role';

    public const REASON_SELF_DISABLE = 'self_disable';

    public const REASON_ALREADY_DISABLED = 'already_disabled';

    public const REASON_NOT_DISABLED = 'not_disabled';

    public const REASON_NOT_ADMIN = 'not_admin';

    public const REASON_UNKNOWN_ROLE = 'unknown_role';

    public const REASON_PASSWORD_REQUIRED = 'password_required';

    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            self::REASON_SELF_ROLE => 'You cannot change your own role.',
            self::REASON_SELF_DISABLE => 'You cannot disable yourself.',
            self::REASON_ALREADY_DISABLED => 'This admin is already disabled.',
            self::REASON_NOT_DISABLED => 'This admin is not disabled.',
            self::REASON_NOT_ADMIN => 'This user has no admin profile.',
            self::REASON_UNKNOWN_ROLE => 'Unknown admin role.',
            self::REASON_PASSWORD_REQUIRED => 'A password is required when creating a brand-new admin.',
            default => 'Admin user operation refused.',
        });
    }
}
