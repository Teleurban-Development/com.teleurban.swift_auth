# Changelog

All notable changes to this project will be documented in this file.

## [4.0.0] - "Archipelago" - 2026-04-27

### Breaking Changes

-   **ADDED (Required):** `equidna/bee-hive ^2.0` is now a required dependency.
    -   Install via `composer require equidna/swift-auth:^4.0` (pulls bee-hive automatically).
    -   The `BeeHiveServiceProvider` is registered automatically by SwiftAuth's service provider.
-   **CHANGED:** `User` and `Role` models now use the `BelongsToTenant` trait.
    -   All queries on `User` and `Role` are automatically scoped to the current tenant via `TenantScope`.
    -   Existing code that queries across all tenants must now call `->withoutGlobalScope(TenantScope::class)` or disable multi-tenancy in config.
-   **CHANGED:** New migration required — `2026_04_26_000001_add_tenant_columns_to_swift_auth_tables.php`.
    -   Adds `id_tenant` (default `'global'`) to `{prefix}Users` and `{prefix}Roles` tables with index.
    -   Must be run before application boots: `php artisan migrate`.
-   **CHANGED:** `SwiftSessionAuth` session structure — login now stores `swift_auth_tenant_id` in session; logout clears it.
    -   Active v3.x sessions will not have this key; they will resolve the tenant via fallback on next request.
-   **CHANGED:** Controllers now return `Responsable` using `ResponseHelper`.
    -   JSON response shapes have been normalised. Applications consuming raw JSON from SwiftAuth controllers should verify compatibility.
-   **CHANGED:** SwiftAuth now throws typed exceptions from `Equidna\Toolkit\Exceptions\*` (`BadRequestException`, `ForbiddenException`, `NotFoundException`, `UnauthorizedException`).
    -   Code catching bare `\Exception` from SwiftAuth operations should be updated to catch the specific types.

### Added

-   **Multi-Tenancy (BeeHive Integration):**
    -   `SwiftAuthTenantResolver` class with 5-level resolution priority: `X-Tenant-Id` header → `tenant_id` query → session → `user->id_tenant` → config fallback (`'global'`).
    -   New `multi_tenancy` config block in `config/swift-auth.php` with keys: `enabled`, `tenant_key`, `resolver`, `fallback_tenant_id`, `session_key`, `request_sources`.
    -   `BelongsToTenant` trait on `User` and `Role` models — auto-assigns `id_tenant` on `creating` Eloquent event via `TenantContext::get()`.
    -   Global `TenantScope` applied to `User` and `Role` queries.
    -   New migration: `2026_04_26_000001_add_tenant_columns_to_swift_auth_tables.php`.
    -   `swift-auth:install` now publishes BeeHive configuration.
-   **Equidna Toolkit Layer (`src/Toolkit/`):**
    -   `ResponseHelper` — unified JSON / redirect response builder for consistent API + Blade/Inertia responses.
    -   `EquidnaFormRequest` — base FormRequest class for all SwiftAuth form requests.
    -   Typed exceptions: `BadRequestException` (400), `ForbiddenException` (403), `NotFoundException` (404), `UnauthorizedException` (401).
    -   `IlluminateHttpRequest.stub` — PHPStan stub for improved static analysis of Illuminate request in package context.
-   **Tests:**
    -   Feature test: `SwiftSessionAuthFlowTest` covering end-to-end login flows.
    -   Unit tests: `MfaServiceTest`, `SessionManagerTest`, `UserTokenServiceTest` (103 total unit tests, 191 assertions passing).
-   `equidna/bee-hive ^2.0` added to `require` in `composer.json`.
-   `illuminate/http`, `illuminate/routing`, `inertiajs/inertia-laravel ^3.0` added to `require`.

### Changed

-   **`SwiftSessionAuth`:**
    -   `login()` refactored into focused helper methods (`initializeLoginSession()`, `finalizeRememberMe()`, `dispatchLoginEvent()`).
    -   Stores `swift_auth_tenant_id` in session on login (if multi-tenancy enabled).
    -   Clears `swift_auth_tenant_id` from session on logout.
-   **`SessionManager`:** Tightened cache/DB validation with improved error logging.
-   **`RememberMeService`:** Updated with token queuing support.
-   **Controllers:** `AuthController`, `MfaController`, `PasswordController` now use `ResponseHelper`; return `Responsable`.
-   **`SelectiveRender` trait:** Returns `Responsable`; improved TypeScript/JavaScript frontend detection.
-   **`SwiftAuthServiceProvider`:**
    -   Registers `BeeHiveServiceProvider`.
    -   Registers `UserRepositoryInterface → EloquentUserRepository` binding.
    -   Exposes `Equidna\Toolkit\` autoloading namespace.
    -   PHPStan enhanced with stub configuration.
-   Relaxed/bumped composer constraints: `equidna/bird-flock ^1.2`, `illuminate/*`, `laravel/helpers`.

### Fixed

-   **Cross-tenant cache pollution** in `UserController::rolesCacheKey()` — was reading tenant from session only; now uses `TenantContext::get()` to prevent one tenant's cached roles leaking to another.
-   Unit tests for `MfaServiceTest`, `SessionManagerTest`, `UserTokenServiceTest`, `ChecksRateLimitsTest` updated for accuracy.

### Security

-   **Tenant isolation enforced at Eloquent layer** via global `TenantScope` — not just at controller level.
-   Tenant context stored in PHP session (server-side), not in client cookies.
-   Cross-tenant cache poisoning vector eliminated in role caching.

### Documentation

-   Complete rewrite of all `doc/*.md` files:
    -   `doc/architecture-diagrams.md` — C4 context/container, login sequence, session state, multi-tenancy flowchart.
    -   `doc/api-documentation.md` — full endpoint reference including WebAuthn, UserToken, admin routes.
    -   `doc/artisan-commands.md` — all 8 Artisan commands documented.
    -   `doc/business-logic-and-core-processes.md` — login, password reset, MFA, session lifecycle, RBAC, multi-tenancy, remember-me, API tokens.
    -   `doc/deployment-instructions.md` — step-by-step install and production guide.
    -   `doc/monitoring.md` — events, log channels, rate limit signals, scheduler monitoring, APM.
    -   `doc/open-questions-and-assumptions.md` — 10 open questions, 10 explicit assumptions.
    -   `doc/routes-documentation.md` — all route groups, middleware, prefixes.
    -   `doc/tests-documentation.md` — test infrastructure, test cases, patterns.
    -   `README.md` — rewritten with v4.0 quick-start, multi-tenancy section, full feature table.

---

## [3.0.0] - "Sovereign" - 2025-01-XX

### Breaking Changes

-   **REMOVED:** `laravel/sanctum` dependency completely removed from package
    -   Sanctum API token system replaced with native `UserToken` implementation
    -   Migration path: See BREAKING_CHANGES.md for detailed upgrade instructions
    -   Applications using Sanctum must migrate to SwiftAuth's UserToken system
-   **REMOVED:** Sanctum migrations no longer published during installation
-   **CHANGED:** API authentication now uses `SwiftAuth.AuthenticateWithToken` middleware instead of Sanctum's

### Added

-   **Native API Authentication System:**
    -   `UserToken` model for API token management with SHA-256 hashing
    -   `UserTokenService` for token CRUD operations (create, validate, revoke, purge)
    -   `create_user_tokens_table` migration with proper indexes and foreign keys
    -   Token abilities/scopes system for fine-grained permissions
    -   Token expiration and usage tracking (`last_used_at`, `expires_at`)
-   **Authentication Middleware:**
    -   `SwiftAuth.AuthenticateWithToken` - Bearer token validation middleware
    -   `SwiftAuth.CheckTokenAbilities` - Ability-based authorization middleware
-   **Comprehensive Localization System:**
    -   Full English (en) and Spanish (es) translation support
    -   10 translation files: `auth.php`, `email.php`, `session.php`, `user.php`, `role.php` (per language)
    -   `LocaleController` for dynamic language switching via POST `/locale/{locale}`
    -   `ShareInertiaData` middleware to share translations with Inertia.js
    -   `LanguageSwitcher` React component (TypeScript + JavaScript versions)
    -   JavaScript/TypeScript translation helpers (`translations.ts`, `translations.js`)
    -   Session-based locale persistence across requests
-   **Documentation:**
    -   `doc/securing-routes.md` - Comprehensive 400+ line guide for route protection
    -   `doc/localization.md` - Complete localization implementation guide
    -   Updated `README.md` with security quick reference
    -   Updated all existing docs to reflect UserToken system

### Changed

-   **Installation Command:**
    -   `InstallSwiftAuth` now publishes translation files (`swift-auth:lang` tag)
    -   Removed Sanctum migration publish step
    -   Updated documentation messages for admin user creation
    -   Migration publishing now groups all migrations together before running `migrate`
-   **Admin Command:**
    -   `CreateAdminUser` no longer accepts password as CLI argument (security improvement)
    -   Always prompts securely for password using `secret()` helper
    -   Auto-generates secure random password if left empty
    -   Removed environment variable fallback (`SWIFT_ADMIN_NAME`, `SWIFT_ADMIN_EMAIL`)
    -   Command signature updated: `swift-auth:create-admin {name} {email}`
-   **Service Provider:**
    -   Locale restoration on boot from session storage
    -   Registered new middleware: `SwiftAuth.ShareInertiaData`, `SwiftAuth.AuthenticateWithToken`, `SwiftAuth.CheckTokenAbilities`
    -   Removed `configureSanctum()` method
    -   Added translation file loading and sharing
-   **Routes:**
    -   Consolidated email verification routes into main `swift-auth.php` file
    -   Deleted separate `swift-auth-email-verification.php` route file
    -   Added locale switching route: `POST /swift-auth/locale/{locale}`
-   **Controllers:**
    -   Updated all controller responses to use translation keys
    -   `AuthController` now uses `__('swift-auth::auth.login_success')` format
    -   `EmailVerificationController` fully internationalized with proper rate limiting
    -   `PasswordController` updated rate limit defaults (3 attempts per 300 seconds)
    -   All Inertia component paths updated to new naming convention
-   **Views & Frontend:**
    -   All Blade email templates internationalized (`@lang` directives)
    -   All Inertia React components updated with `__()` translation helper
    -   Removed hardcoded Spanish/English strings from UI
    -   Login, register, password reset forms fully localized
-   **Code Quality:**
    -   Fixed 6 identified issues: SHA-256 consistency, debug code removal, documentation updates, index optimization, config references, rate limit validation
    -   Consolidated database indexes into original migration files
    -   Removed empty constructor bodies, added single-line placeholder comments
    -   Improved import organization across all files
-   **Notifications:**
    -   Email subjects now use translation keys (`__('swift-auth::email.reset_subject')`)
    -   Support for locale-based email content
-   **Architecture:**
    -   Updated all architecture diagrams with UserTokenService
    -   Added UserTokens table to ERD
    -   Updated API flow documentation

### Fixed

-   Rate limiting validation in `PasswordController` (enforces numeric values)
-   Database indexes now created inline with original migrations (performance optimization)
-   Configuration key references consistent across codebase
-   SelectiveRender trait now correctly handles TypeScript/JavaScript frontend detection
-   DTO constructor formatting (empty constructors with placeholder comments)

### Security

-   **Admin password handling:** Never accepts passwords as CLI arguments
-   **Token hashing:** Uses SHA-256 for all stored tokens (consistent with RememberToken, PasswordResetToken)
-   **Ability checks:** Fine-grained permission system for API routes
-   **Expiration tracking:** Automatic token expiration with configurable TTL

### Documentation

-   Added comprehensive route security guide with examples
-   Added localization implementation guide
-   Updated README with UserToken quick reference
-   Updated API documentation with UserToken endpoints
-   Updated architecture diagrams
-   Updated deployment instructions
-   Updated artisan commands documentation

### Deprecated

-   None

### Removed

-   `laravel/sanctum` package dependency
-   Sanctum migration publishing
-   `create_sanctum_api_tokens_table.php` migration
-   `configureSanctum()` method from `SwiftAuthServiceProvider`
-   `swift-auth-email-verification.php` route file (consolidated)
-   Environment variable fallback for admin creation

---

## [2.0.0] - 2025-12-15

### Changed

-   **Breaking:** Enforced strict Domain-Driven Design structure in `src/Classes/`. (Classes moved to `Auth/`, `Notifications/`, `Users/` domains).
-   **Breaking:** Strict Coding Standards adoption (PSR-12 + Custom Rules).
    -   Enforced return types on all methods.
    -   Constructor property promotion widely adopted.
    -   Removed redundant PHPDoc where native types exist.
-   **Breaking:** File-level DocBlocks are now mandatory and standardized.
-   **Documentation:** Massive cleanup of PHPDoc to reduce noise and rely on Type Hints.

### Added

-   **Docs:** Complete architectural documentation (`/doc` folder) covering API, Deployment, Monitoring, and Business Logic.
-   **Events:** Added missing file-level DocBlocks to Auth Events (`UserLoggedIn`, `UserLoggedOut`, etc.).

### Fixed

-   False positive lint errors in Controllers regarding Facade usage.
-   Redundant documentation in DTOs and Services.

## [1.0.3] - 2025-12-12

### Added

-   **Test Infrastructure**: Complete test environment with Orchestra Testbench
    -   Database migrations now run automatically in tests
    -   All 5 package migrations (Users, Roles, Sessions, RememberTokens, PasswordResetTokens)
    -   In-memory SQLite database for fast testing
    -   Test helpers available globally via TestHelpers trait
-   **External Dependencies**: BirdFlock facade stub for testing without external packages
-   **Test Coverage**: 99/168 tests passing (59%), focused on unit and service layer

### Changed

-   **Code Quality**: PHPStan Level 5 analysis with zero errors
    -   Added facade type aliases for better static analysis
    -   Fixed TokenMetadataValidator to use explicit count check
    -   Removed unused private methods (recordUserSession, deleteUserSession)
-   **Code Style**: 100% PSR-12 compliance via PHPCS
    -   All 16 formatting violations auto-fixed
    -   Consistent code style across entire codebase
-   **Tests**: Converted model tests to database-backed tests
    -   UserTest now uses RefreshDatabase trait
    -   Real Eloquent relationships instead of mocks
    -   More realistic test scenarios

### Fixed

-   Test environment configuration for encryption keys and app settings
-   BirdFlock class not found errors in feature tests
-   Role search test case sensitivity issue
-   Missing 'name' field in User model test creation

### Infrastructure

-   PHPStan configuration updated with facade recognition
-   PHPCS/PHPCBF configured for PSR-12 with 250 char line limit
-   Tests now extend package TestCase with full Laravel services
-   Test database properly configured with empty table prefix

---

## [1.0.2] - 2025-11-20

### Changed

-   Config: removed `password_rules` and consolidated password policy into `password_min_length`.
-   Config: added optional `hash_driver` option to allow explicit hash backend selection.
-   Code: controllers and commands now use `password_min_length` and respect `hash_driver` when present.
-   Docs: updated `README.md` and added `UPGRADING.md` with upgrade notes and provider instructions.
-   Lint: ensured PSR-12 compliance via PHPCS.

---

## [1.0.1] - 2025-11-17

### Changed

-   Version bump for maintenance and dependency updates (see composer.json).

### Added

-   Initial stable release: authentication and authorization for Laravel projects
-   Admin creation command with secure password handling
-   Password reset flow with TTL and rate-limiting
-   Configurable frontend (Blade, TypeScript, JavaScript)
-   Queue-based password reset emails
-   PSR-12 code style and unit test guidance

### Changed (1.0.0)

-   Namespace standardized to `Equidna\SwiftAuth`
-   Removed legacy admin password config for security

### Security

-   No plaintext password exposure for admin creation
-   Password reset tokens and rate-limiting improvements

---

See previous commits for pre-1.0.0 changes.
