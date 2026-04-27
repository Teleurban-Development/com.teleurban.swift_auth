# SwiftAuth

**Bottled authentication for Laravel projects.**

SwiftAuth is a production-ready authentication package for **Laravel 11 and 12** that provides a complete, drop-in identity management layer. It ships session-based authentication, multi-factor authentication (OTP and WebAuthn / Passkeys), role-based access control, concurrent session management with configurable limits and eviction strategies, account lockout, password reset, email verification, remember-me tokens, API token issuance (Sanctum-compatible), and multi-tenancy via **BeeHive** — all configurable through a single published config file and accessible through a clean Facade.

**Package type:** Composer library (not a standalone application).
**Namespace:** `Equidna\SwiftAuth\`.
**Service provider:** `Equidna\SwiftAuth\Providers\SwiftAuthServiceProvider` (auto-discovered via `composer.json`).

---

## Documentation Index

- [Deployment Instructions](doc/deployment-instructions.md)
- [Securing Routes](doc/securing-routes.md) — Session & API Token Authentication
- [API Documentation](doc/api-documentation.md)
- [Routes Documentation](doc/routes-documentation.md)
- [Artisan Commands](doc/artisan-commands.md)
- [Tests Documentation](doc/tests-documentation.md)
- [Architecture Diagrams](doc/architecture-diagrams.md)
- [Monitoring](doc/monitoring.md)
- [Business Logic & Core Processes](doc/business-logic-and-core-processes.md)
- [Open Questions & Assumptions](doc/open-questions-and-assumptions.md)

> This documentation and the codebase follow the project's **Coding Standards Guide** and **PHPDoc Style Guide**.

---

## Tech Stack & Requirements

| Property | Value                                                    |
| -------- | -------------------------------------------------------- |
| Type     | Laravel Package (Composer library)                       |
| PHP      | ^8.2, ^8.3, ^8.4                                         |
| Laravel  | 11.x / 12.x                                              |
| Frontend | Blade, Inertia + TypeScript, or Inertia + JavaScript     |
| Database | Any Laravel-supported driver (SQLite, MySQL, PostgreSQL) |
| Cache    | Any Laravel cache driver                                 |
| Queue    | Not required (operations are synchronous by default)     |

**Key dependencies:**

- `equidna/bee-hive ^2.0` — Multi-tenancy (BelongsToTenant trait, TenantScope, TenantContext)
- `equidna/bird-flock ^1.2` — Notification and email dispatch bus
- `equidna/toolkit` — Shared helpers: ResponseHelper, exceptions
- `laragear/webauthn ^5.0` — WebAuthn / Passkey support
- `laravel/sanctum ^4.3` — API token authentication
- `inertiajs/inertia-laravel ^3.0` — Inertia.js SPA adapter

---

## Quick Start

1.  **Install the package:**

    ```bash
    composer require equidna/swift-auth
    ```

2.  **Run the install command** (publishes config, runs migrations):

    ```bash
    php artisan swift-auth:install
    ```

3.  **Configure environment variables** in `.env`:

    ```env
    SWIFT_AUTH_FRONTEND=typescript          # blade | typescript | javascript
    SWIFT_AUTH_SUCCESS_URL=/dashboard
    SWIFT_AUTH_ALLOW_REGISTRATION=false
    SWIFT_AUTH_TABLE_PREFIX=swift-auth_
    SWIFT_AUTH_ROUTE_PREFIX=swift-auth
    ```

4.  **Run migrations** (if not already run by the installer):

    ```bash
    php artisan migrate
    ```

5.  **Create an initial admin user:**

    ```bash
    php artisan swift-auth:create-admin
    ```

6.  **Start the application:**

    ```bash
    php artisan serve
    ```

    Navigate to `/{route-prefix}/login` (default: `/swift-auth/login`).

---

## Using the Facade

```php
use Equidna\SwiftAuth\Support\Facades\SwiftAuth;

// Check authentication
if (SwiftAuth::check()) {
    $user = SwiftAuth::user();
    $userId = SwiftAuth::id();
}

// Permission checks
SwiftAuth::canPerformAction('sw-admin');
SwiftAuth::hasRole('administrator');

// Session management
$sessions = SwiftAuth::sessionsForUser($userId);
SwiftAuth::revokeSession($userId, $sessionId);

// Manual login/logout
SwiftAuth::login($user, $ip, $userAgent, $deviceName, remember: true);
SwiftAuth::logout();
```

---

## Protecting Routes

**Session-based (web) authentication:**

```php
Route::middleware('SwiftAuth.RequireAuthentication')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
});

// With action-based authorization
Route::middleware(['SwiftAuth.RequireAuthentication', 'SwiftAuth.CanPerformAction:sw-admin'])
    ->group(function () {
        Route::get('/admin', [AdminController::class, 'index']);
    });
```

**API token authentication:**

```php
Route::middleware('SwiftAuth.AuthenticateWithToken')->group(function () {
    Route::get('/api/profile', [ProfileController::class, 'show']);
});

// With token ability check
Route::middleware(['SwiftAuth.AuthenticateWithToken', 'SwiftAuth.CheckTokenAbilities:posts:write'])
    ->group(function () {
        Route::post('/api/posts', [PostController::class, 'store']);
    });
```

For full reference, see [Securing Routes](doc/securing-routes.md).

---

## Localization

SwiftAuth ships translations for **English (en)** and **Spanish (es)**. Locale is persisted in the user session and can be switched at runtime via the `POST /{prefix}/locale/{locale}` endpoint.

**In PHP / Blade:**

```php
__('swift-auth::auth.login_title')
```

**In TypeScript / JavaScript:**

```typescript
import { __ } from "../../../lang/translations";
<h1>{__("auth.login_title")}</h1>
```

For comprehensive localization documentation, see [Localization Guide](doc/localization.md).
