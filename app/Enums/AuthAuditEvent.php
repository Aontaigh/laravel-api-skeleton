<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Authentication events recorded in the auth audit log.
 */
enum AuthAuditEvent: string
{
    case Login = 'Login';
    case LoginFailed = 'Login Failed';
    case Logout = 'Logout';
    case ForcedLogout = 'Forced Logout';
    case Register = 'Register';
    case RememberMeLogin = 'Remember Me Login';
    case ClientTokenExchange = 'Client Token Exchange';
    case ClientTokenExchangeFailed = 'Client Token Exchange Failed';
    case TwoFactorIssued = 'Two-Factor Issued';
    case TwoFactorVerified = 'Two-Factor Verified';
    case TwoFactorFailed = 'Two-Factor Failed';
}
