# Deployment Instructions

> This document covers installing, configuring, and deploying the `equidna/swift-auth` package into a Laravel 11 or 12 application.

---

## Prerequisites

| Requirement  | Version                                              |
| ------------ | ---------------------------------------------------- |
| PHP          | ^8.2, ^8.3, ^8.4                                     |
| Laravel      | 11.x / 12.x                                          |
| Composer     | 2.x                                                  |
| Database     | MySQL 8+, PostgreSQL 14+, or SQLite (tests)          |
| Cache driver | Any Laravel-supported driver (database, redis, etc.) |

**Required package dependencies** (resolved automatically by Composer):

- `equidna/bee-hive ^2.0`
- `equidna/bird-flock ^1.2`
- `equidna/toolkit` (latest)
- `laragear/webauthn ^5.0`
- `laravel/sanctum ^4.3`
- `inertiajs/inertia-laravel ^3.0`

---

## Installation

### Step 1 — Require the package

```bash
composer require equidna/swift-auth
```

The service provider (`Equidna\SwiftAuth\Providers\SwiftAuthServiceProvider`) is auto-discovered via the `extra.laravel.providers` key in `composer.json`.

### Step 2 — Run the installer

```bash
php artisan swift-auth:install
```

The `swift-auth:install` command will:

1. Interactively prompt for key configuration options.
2. Publish the config file to `config/swift-auth.php`.
3. Optionally publish migrations, views, language files, and frontend assets.
4. Run `php artisan migrate` for you (optional confirmation prompt).

### Step 3 — Run migrations manually (if skipped above)

```bash
php artisan migrate
```

### Step 4 — Create the first admin user

```bash
php artisan swift-auth:create-admin
```

---

## Environment Variables

All SwiftAuth configuration can be driven from `.env`. Below is a full reference with defaults.

### Core

| Variable                        | Default       | Description                                           |
| ------------------------------- | ------------- | ----------------------------------------------------- |
| `SWIFT_AUTH_FRONTEND`           | `blade`       | Frontend adapter: `blade`, `typescript`, `javascript` |
| `SWIFT_AUTH_ALLOW_REGISTRATION` | `false`       | Enable public self-registration endpoint              |
| `SWIFT_AUTH_SUCCESS_URL`        | `/`           | Redirect URL after successful login                   |
| `SWIFT_AUTH_ROUTE_PREFIX`       | `swift-auth`  | URL prefix for all package routes                     |
| `SWIFT_AUTH_TABLE_PREFIX`       | `swift-auth_` | Database table prefix                                 |
| `SWIFT_AUTH_DEFAULT_ROLE_ID`    | `null`        | Role ID assigned to new registered users              |

### Session Lifetimes

| Variable                               | Default | Description                            |
| -------------------------------------- | ------- | -------------------------------------- |
| `SWIFT_AUTH_SESSION_IDLE_LIFETIME`     | `900`   | Seconds before idle session expires    |
| `SWIFT_AUTH_SESSION_ABSOLUTE_LIFETIME` | `28800` | Seconds before absolute session expiry |

### Session Limits

| Variable                      | Default  | Description                                          |
| ----------------------------- | -------- | ---------------------------------------------------- |
| `SWIFT_AUTH_MAX_SESSIONS`     | `5`      | Maximum concurrent sessions per user (0 = unlimited) |
| `SWIFT_AUTH_SESSION_EVICTION` | `oldest` | Eviction strategy: `oldest` or `newest`              |

### Rate Limiting

| Variable                          | Default | Description                        |
| --------------------------------- | ------- | ---------------------------------- |
| `SWIFT_AUTH_LOGIN_EMAIL_ATTEMPTS` | `3`     | Max login attempts per email       |
| `SWIFT_AUTH_LOGIN_EMAIL_DECAY`    | `300`   | Lockout window (seconds) per email |
| `SWIFT_AUTH_LOGIN_IP_ATTEMPTS`    | `10`    | Max login attempts per IP          |
| `SWIFT_AUTH_LOGIN_IP_DECAY`       | `300`   | Lockout window (seconds) per IP    |

### Account Lockout

| Variable                          | Default | Description                                  |
| --------------------------------- | ------- | -------------------------------------------- |
| `SWIFT_AUTH_LOCKOUT_ENABLED`      | `true`  | Enable account lockout after failed attempts |
| `SWIFT_AUTH_LOCKOUT_MAX_ATTEMPTS` | `5`     | Failed attempts before lockout               |
| `SWIFT_AUTH_LOCKOUT_DURATION`     | `900`   | Lockout duration in seconds                  |

### Password Reset

| Variable                        | Default | Description                        |
| ------------------------------- | ------- | ---------------------------------- |
| `SWIFT_AUTH_PASSWORD_RESET_TTL` | `3600`  | Token validity in seconds (1 hour) |

### Remember Me

| Variable                        | Default   | Description                         |
| ------------------------------- | --------- | ----------------------------------- |
| `SWIFT_AUTH_REMEMBER_ME`        | `true`    | Enable remember-me functionality    |
| `SWIFT_AUTH_REMEMBER_TOKEN_TTL` | `2592000` | Token lifetime in seconds (30 days) |

### MFA

| Variable                 | Default | Description                      |
| ------------------------ | ------- | -------------------------------- |
| `SWIFT_AUTH_MFA_ENABLED` | `false` | Enable MFA challenge after login |
| `SWIFT_AUTH_MFA_DRIVER`  | `otp`   | MFA driver: `otp` or `webauthn`  |

### Multi-Tenancy

| Variable                           | Default  | Description                                       |
| ---------------------------------- | -------- | ------------------------------------------------- |
| `SWIFT_AUTH_MULTI_TENANCY_ENABLED` | `false`  | Enable tenant isolation                           |
| `SWIFT_AUTH_FALLBACK_TENANT_ID`    | `global` | Tenant ID used when no tenant context is resolved |

### Email Verification

| Variable                        | Default | Description                            |
| ------------------------------- | ------- | -------------------------------------- |
| `SWIFT_AUTH_EMAIL_VERIFICATION` | `false` | Require email verification on register |

### Session Cleanup (Scheduler)

| Variable                               | Default | Description                                 |
| -------------------------------------- | ------- | ------------------------------------------- |
| `SWIFT_AUTH_SESSION_CLEANUP_FREQUENCY` | `daily` | Scheduler frequency for stale session purge |

---

## Publishing Assets

Run `php artisan vendor:publish --tag=<tag>` for each group you need:

| Tag                     | Contents                                            |
| ----------------------- | --------------------------------------------------- |
| `swift-auth:config`     | `config/swift-auth.php`                             |
| `swift-auth:migrations` | All database migration files                        |
| `swift-auth:views`      | Blade views to `resources/views/vendor/swift-auth/` |
| `swift-auth:lang`       | Language files to `lang/vendor/swift-auth/`         |
| `swift-auth:models`     | Eloquent model stubs to `app/Models/`               |
| `swift-auth:ts-react`   | TypeScript/React pages to `resources/`              |
| `swift-auth:js-react`   | JavaScript/React pages to `resources/`              |
| `swift-auth:icons`      | Icon assets to `resources/`                         |

---

## Database Migrations

SwiftAuth ships 7 migration files. The table prefix for all tables is controlled by `config('swift-auth.table_prefix')` (default: `swift-auth_`).

| File                                          | Table(s) Created                           |
| --------------------------------------------- | ------------------------------------------ |
| `create_users_table.php`                      | `{prefix}Users`                            |
| `create_roles_table.php`                      | `{prefix}Roles`                            |
| `create_sessions_table.php`                   | `{prefix}Sessions`                         |
| `create_remember_tokens_table.php`            | `{prefix}RememberTokens`                   |
| `create_password_reset_tokens_table.php`      | `{prefix}PasswordResetTokens`              |
| `create_user_tokens_table.php`                | `{prefix}UserTokens`                       |
| `add_tenant_columns_to_swift_auth_tables.php` | Adds `id_tenant` column to Users and Roles |

The tenant migration should run after `create_users_table` and `create_roles_table`. All migrations read the configured prefix at migration time, so changing the prefix requires fresh migrations.

---

## Scheduler Setup

SwiftAuth registers two scheduled tasks automatically. Ensure the Laravel scheduler is running:

```bash
# Add to crontab (Linux/macOS)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

```powershell
# Windows Task Scheduler (every minute)
php artisan schedule:run
```

| Task                 | Frequency                     | Command                           |
| -------------------- | ----------------------------- | --------------------------------- |
| Purge expired tokens | Hourly                        | `swift-auth:purge-expired-tokens` |
| Purge stale sessions | Configurable (default: daily) | `swift-auth:purge-stale-sessions` |

The stale-session purge frequency is controlled by `config('swift-auth.session_cleanup.frequency')` (env: `SWIFT_AUTH_SESSION_CLEANUP_FREQUENCY`).

---

## Middleware Registration

The following middleware aliases are registered automatically by the service provider:

| Alias                             | Class                                   | Purpose                           |
| --------------------------------- | --------------------------------------- | --------------------------------- |
| `SwiftAuth.RequireAuthentication` | `Http\Middleware\RequireAuthentication` | Block unauthenticated requests    |
| `SwiftAuth.CanPerformAction`      | `Http\Middleware\CanPerformAction`      | Action-based authorization        |
| `SwiftAuth.SecurityHeaders`       | `Http\Middleware\SecurityHeaders`       | Inject security HTTP headers      |
| `SwiftAuth.ShareInertiaData`      | `Http\Middleware\ShareInertiaData`      | Share auth state with Inertia     |
| `SwiftAuth.AuthenticateWithToken` | `Http\Middleware\AuthenticateWithToken` | API token authentication          |
| `SwiftAuth.CheckTokenAbilities`   | `Http\Middleware\CheckTokenAbilities`   | Token ability-based authorization |

All package routes are automatically wrapped in `SwiftAuth.SecurityHeaders`.

---

## Multi-Tenancy Setup

To enable tenant isolation:

1.  Set `SWIFT_AUTH_MULTI_TENANCY_ENABLED=true`.
2.  Configure a tenant resolver in `config/swift-auth.php` (defaults to `SwiftAuthTenantResolver`).
3.  The default resolver reads the tenant ID from, in priority order:
    - `X-Tenant-Id` HTTP header
    - `tenant_id` query parameter
    - Session key (`swift_auth_tenant_id`)
    - Authenticated user's `id_tenant` field
    - Fallback tenant ID (`config('swift-auth.multi_tenancy.fallback_tenant_id')`, default: `global`)

All `User` and `Role` models automatically apply a global scope that filters records by `id_tenant`. The `BelongsToTenant` trait also auto-assigns `id_tenant` on model creation via the resolved `TenantContext`.

---

## Upgrading

See [CHANGELOG.md](../CHANGELOG.md) and [BREAKING_CHANGES.md](../BREAKING_CHANGES.md) before upgrading.

After pulling a new version:

```bash
composer update equidna/swift-auth
php artisan migrate
php artisan vendor:publish --tag=swift-auth:config --force
```

Review config diffs for any new keys and add them to `.env` as needed.
