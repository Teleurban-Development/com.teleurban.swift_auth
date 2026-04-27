<?php

use Equidna\SwiftAuth\Support\Tenancy\SwiftAuthTenantResolver;

/**
 * Package configuration for SwiftAuth.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Frontend Stack
    |--------------------------------------------------------------------------
    |
    | Supported values: "typescript", "javascript", "blade"
    |
    */
    'frontend' => env('SWIFT_AUTH_FRONTEND', 'typescript'),

    /*
    |--------------------------------------------------------------------------
    | Enable public registration
    |--------------------------------------------------------------------------
    */
    'allow_registration' => env('SWIFT_AUTH_ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Success Redirect URL
    |--------------------------------------------------------------------------
    */
    'success_url' => env('SWIFT_AUTH_SUCCESS_URL', '/'),

    /*
    |--------------------------------------------------------------------------
    | Login Rate Limits
    |--------------------------------------------------------------------------
    */
    'login_rate_limits' => [
        'email' => [
            'attempts' => 3,
            'decay_seconds' => 300,
        ],
        'ip' => [
            'attempts' => 10,
            'decay_seconds' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Lifetimes
    |--------------------------------------------------------------------------
    */
    'session_lifetimes' => [
        'idle_timeout_seconds' => env('SWIFT_AUTH_SESSION_IDLE_TIMEOUT', 900), // 15 minutes
        'absolute_timeout_seconds' => env('SWIFT_AUTH_SESSION_ABSOLUTE_TIMEOUT', 28800), // 8 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Cleanup
    |--------------------------------------------------------------------------
    */
    'session_cleanup' => [
        'enabled' => env('SWIFT_AUTH_SESSION_CLEANUP_ENABLED', true),
        'grace_seconds' => env('SWIFT_AUTH_SESSION_CLEANUP_GRACE', 0),
        'schedule' => env('SWIFT_AUTH_SESSION_CLEANUP_CRON', 'daily'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrent Session Limits
    |--------------------------------------------------------------------------
    */
    'session_limits' => [
        'max_sessions' => env('SWIFT_AUTH_MAX_SESSIONS', null),
        'eviction' => env('SWIFT_AUTH_SESSION_EVICTION', 'oldest'), // oldest | newest
    ],

    /*
    |--------------------------------------------------------------------------
    | Remember Me Tokens
    |--------------------------------------------------------------------------
    |
    | Token-based remember-me implementation using the remember_tokens table.
    |
    */
    'remember_me' => [
        'enabled' => env('SWIFT_AUTH_REMEMBER_ENABLED', true),
        'cookie_name' => env('SWIFT_AUTH_REMEMBER_COOKIE', 'swift_auth_remember'),
        'ttl_seconds' => env('SWIFT_AUTH_REMEMBER_TTL', 1209600), // 14 days
        'rotate_on_use' => env('SWIFT_AUTH_REMEMBER_ROTATE', true),
        'secure' => env('SWIFT_AUTH_REMEMBER_SECURE', true),
        'same_site' => env('SWIFT_AUTH_REMEMBER_SAMESITE', 'strict'),
        'domain' => env('SWIFT_AUTH_REMEMBER_DOMAIN', null),
        'path' => env('SWIFT_AUTH_REMEMBER_PATH', '/'),
        'policy' => env('SWIFT_AUTH_REMEMBER_POLICY', 'strict'), // strict|lenient
        'allow_same_subnet' => env('SWIFT_AUTH_REMEMBER_ALLOW_SUBNET', true),
        'subnet_mask' => env('SWIFT_AUTH_REMEMBER_SUBNET_MASK', 24),
        'device_header' => env('SWIFT_AUTH_REMEMBER_DEVICE_HEADER', 'X-Device-Id'),
        'require_device_header' => env('SWIFT_AUTH_REMEMBER_REQUIRE_DEVICE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Factor Authentication (MFA)
    |--------------------------------------------------------------------------
    */
    'mfa' => [
        'enabled' => env('SWIFT_AUTH_MFA_ENABLED', false),
        'driver' => env('SWIFT_AUTH_MFA_DRIVER', 'otp'), // otp | webauthn
        'verification_url' => env('SWIFT_AUTH_MFA_VERIFICATION_URL', '/mfa/verify'),
        'pending_user_session_key' => 'swift_auth_pending_user_id',
        'pending_method_session_key' => 'swift_auth_pending_mfa_method',

        'otp' => [
            'verification_url' => env('SWIFT_AUTH_OTP_VERIFICATION_URL', null),
            'driver' => env('SWIFT_AUTH_OTP_DRIVER', 'otp'),
        ],

        'webauthn' => [
            'verification_url' => env('SWIFT_AUTH_WEBAUTHN_VERIFICATION_URL', null),
            'driver' => env('SWIFT_AUTH_WEBAUTHN_DRIVER', 'webauthn'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Reset Tokens
    |--------------------------------------------------------------------------
    */
    'password_reset_ttl' => env('SWIFT_AUTH_PASSWORD_RESET_TTL', 900),

    'password_reset_rate_limit' => [
        'attempts' => 3,
        'decay_seconds' => 300,
    ],

    'password_reset_verify_attempts' => 5,
    'password_reset_verify_decay_seconds' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Password Requirements
    |--------------------------------------------------------------------------
    */
    'password_min_length' => 12,
    'hash_driver' => null,

    'password_requirements' => [
        'require_letters' => true,
        'require_mixed_case' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'disallow_common_passwords' => true,
        'common_passwords' => [
            'password',
            'password1',
            '123456',
            '12345678',
            '123456789',
            'qwerty',
            'abc123',
            'iloveyou',
            'welcome',
            'admin',
            'letmein',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'sw-admin' => 'Swift Auth admin', // used internally by SwiftAuth core
    ],

    /*
    |--------------------------------------------------------------------------
    | Table & Route Prefix
    |--------------------------------------------------------------------------
    */
    'table_prefix' => env('SWIFT_AUTH_TABLE_PREFIX', 'swift-auth_'),
    'route_prefix' => env('SWIFT_AUTH_ROUTE_PREFIX', 'swift-auth'),

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | List of locale codes SwiftAuth will accept when restoring the session
    | locale. Any locale not in this list will be ignored.
    |
    */
    'supported_locales' => ['en', 'es'],

    /*
    |--------------------------------------------------------------------------
    | Email Verification
    |--------------------------------------------------------------------------
    */
    'email_verification' => [
        'required' => env('SWIFT_AUTH_REQUIRE_VERIFICATION', false),
        'token_ttl' => 86400, // 24 hours
        'resend_rate_limit' => [
            'attempts' => 3,
            'decay_seconds' => 300, // 5 minutes
        ],
        'ip_rate_limit' => [
            'attempts' => 5,
            'decay_seconds' => 60, // 1 minute
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout
    |--------------------------------------------------------------------------
    */
    'account_lockout' => [
        'enabled' => env('SWIFT_AUTH_LOCKOUT_ENABLED', true),
        'max_attempts' => 5,
        'lockout_duration' => 900, // 15 minutes
        'reset_after' => 3600, // Reset counter after 1 hour of no attempts
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Role Assignment
    |--------------------------------------------------------------------------
    */
    'default_role_id' => env('SWIFT_AUTH_DEFAULT_ROLE_ID', null),

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */
    'security_headers' => [
        'csp' => env('SWIFT_AUTH_CSP', null), // null = no CSP header emitted. Strongly recommended to configure, e.g. "default-src 'self'; script-src 'self'"
        'permissions_policy' => env('SWIFT_AUTH_PERMISSIONS_POLICY', null), // e.g., "geolocation=(), microphone=()"
        'hsts' => [
            'enabled' => true,
            'max_age' => 31536000,
            'include_subdomains' => true,
            'preload' => false,
        ],
        'cross_origin_opener_policy' => env('SWIFT_AUTH_COOP', null), // e.g., "same-origin"
        'cross_origin_embedder_policy' => env('SWIFT_AUTH_COEP', null), // e.g., "require-corp"
        'cross_origin_resource_policy' => env('SWIFT_AUTH_CORP', null), // e.g., "same-origin"
        'referrer_policy' => env('SWIFT_AUTH_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy (BeeHive)
    |--------------------------------------------------------------------------
    */
    'multi_tenancy' => [
        'enabled' => env('SWIFT_AUTH_MULTI_TENANCY_ENABLED', false),
        'tenant_key' => env('SWIFT_AUTH_TENANT_KEY', 'id_tenant'),
        'resolver' => env(
            'SWIFT_AUTH_TENANT_RESOLVER',
            SwiftAuthTenantResolver::class,
        ),
        'fallback_tenant_id' => env('SWIFT_AUTH_FALLBACK_TENANT_ID', 'global'),
        'session_key' => env('SWIFT_AUTH_TENANT_SESSION_KEY', 'swift_auth_tenant_id'),
        'request_sources' => [
            'header' => env('SWIFT_AUTH_TENANT_HEADER', 'X-Tenant-Id'),
            'query' => env('SWIFT_AUTH_TENANT_QUERY', 'tenant_id'),
        ],
    ],
];
