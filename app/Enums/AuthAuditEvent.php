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
    case PasswordResetRequested = 'Password Reset Requested';
    case PasswordReset = 'Password Reset';
    case PasswordResetFailed = 'Password Reset Failed';
    case EmailVerificationSent = 'Email Verification Sent';
    case EmailVerified = 'Email Verified';
    case EmailVerificationFailed = 'Email Verification Failed';
    case PasswordChanged = 'Password Changed';
    case UserSuspended = 'User Suspended';
    case UserUnsuspended = 'User Unsuspended';
    case UserRoleChanged = 'User Role Changed';
    case SessionRevoked = 'Session Revoked';
    case TokenCreated = 'Token Created';
    case TokenRevoked = 'Token Revoked';
    case ApiClientCreated = 'API Client Created';
    case ApiClientUpdated = 'API Client Updated';
    case ApiClientDeleted = 'API Client Deleted';
    case ClientSecretRotated = 'Client Secret Rotated';
    case WebhookEndpointCreated = 'Webhook Endpoint Created';
    case WebhookEndpointUpdated = 'Webhook Endpoint Updated';
    case WebhookEndpointDeleted = 'Webhook Endpoint Deleted';
    case WebhookSecretRotated = 'Webhook Secret Rotated';
}
