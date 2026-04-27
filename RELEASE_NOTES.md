# Release v4.0.0 "Archipelago"

**Release Date:** 2026-04-27
**Codename:** Archipelago
**Type:** Major Release (Breaking Changes)

**SwiftAuth v4.0.0** ("Archipelago") delivers **native multi-tenancy** as a first-class feature through deep integration with `equidna/bee-hive`, an internal Toolkit layer for consistent responses and typed exceptions, and a thoroughly tested auth core. Like an archipelago of isolated islands connected by shared infrastructure, each tenant now gets its own secure space within a single SwiftAuth installation.

---

## Highlights

- **Multi-Tenancy** — Full tenant isolation for `User` and `Role` models via BeeHive's `BelongsToTenant` trait and global `TenantScope`. Five-level tenant resolution: header → query → session → user → fallback.
- **Equidna Toolkit** — `ResponseHelper` unifies all JSON and redirect responses. Four typed exceptions replace generic error handling across all services.
- **Refactored Auth Core** — `SwiftSessionAuth.login()` decomposed into focused helpers; `SessionManager` hardened; `RememberMeService` updated with token queuing.
- **103 Unit Tests Passing** — New `MfaServiceTest`, `SessionManagerTest`, `UserTokenServiceTest` and `SwiftSessionAuthFlowTest` added.
- **Complete Documentation Rewrite** — All 9 `doc/*.md` files regenerated from the live codebase.

---

## Added

### Multi-Tenancy via BeeHive

SwiftAuth now integrates `equidna/bee-hive ^2.0` for production-grade multi-tenancy.

```php
// config/swift-auth.php
'multi_tenancy' => [
    'enabled'           => true,
    'tenant_key'        => 'id_tenant',
    'resolver'          => SwiftAuthTenantResolver::class,
    'fallback_tenant_id'=> 'global',
    'session_key'       => 'swift_auth_tenant_id',
    'request_sources'   => [
        'header' => 'X-Tenant-Id',
        'query'  => 'tenant_id',
    ],
],
```

`SwiftAuthTenantResolver` resolves the active tenant in this priority order:

1. `X-Tenant-Id` HTTP header
2. `tenant_id` query parameter
3. Session key `swift_auth_tenant_id`
4. Authenticated user's `id_tenant`
5. Config fallback (`'global'`)

`User` and `Role` models auto-assign `id_tenant` on creation and scope all queries to the current tenant. Existing single-tenant apps set `multi_tenancy.enabled = false` to use `'global'` as the universal tenant.

### Equidna Toolkit Layer

```php
// Consistent responses everywhere
use Equidna\Toolkit\Helpers\ResponseHelper;

return ResponseHelper::success(['user' => $user], 'Login successful');
return ResponseHelper::error('Invalid credentials', 401);
```

```php
// Typed exceptions — catch exactly what you need
use Equidna\Toolkit\Exceptions\NotFoundException;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
```

---

## Changed

- `SwiftSessionAuth::login()` — refactored into `initializeLoginSession()`, `finalizeRememberMe()`, `dispatchLoginEvent()`.
- `SwiftSessionAuth` now stores/clears `swift_auth_tenant_id` in session on login/logout.
- `SessionManager` — improved cache/DB validation and error logging.
- Controllers return `Responsable` via `ResponseHelper`; JSON shapes normalised.
- `SelectiveRender` trait returns `Responsable` with improved frontend detection.
- PHPStan configuration enhanced with `IlluminateHttpRequest.stub`.

---

## Fixed

- **Cross-tenant cache pollution** in `UserController::rolesCacheKey()` — now uses `TenantContext::get()` instead of reading raw from session, eliminating a potential cross-tenant role cache leak.
- Unit test accuracy improvements for `MfaServiceTest`, `SessionManagerTest`, `UserTokenServiceTest`.

---

## Security

- Tenant isolation enforced at Eloquent layer via global scope — not just at controller/service level.
- Tenant context persisted in PHP session (server-side), not in client-readable cookies.
- Cross-tenant cache poisoning vector closed in role caching.

---

## Breaking Changes Summary

| #   | Change                                                 | Impact                                                |
| --- | ------------------------------------------------------ | ----------------------------------------------------- |
| 1   | New required dependency `equidna/bee-hive`             | `composer require equidna/swift-auth:^4.0`            |
| 2   | `User`/`Role` queries now tenant-scoped globally       | Review cross-tenant queries; use `withoutGlobalScope` |
| 3   | New migration required (`id_tenant` columns)           | Run `php artisan migrate`                             |
| 4   | Session adds `swift_auth_tenant_id` key                | Active v3.x sessions degrade gracefully               |
| 5   | Typed exceptions from `Equidna\Toolkit\Exceptions\*`   | Update `catch` blocks                                 |
| 6   | Normalised JSON response envelope via `ResponseHelper` | Update API clients                                    |

For full migration instructions see [BREAKING_CHANGES.md](BREAKING_CHANGES.md).

---

## Upgrade Path

```bash
# 1. Update the package
composer require equidna/swift-auth:^4.0

# 2. Publish new migration
php artisan vendor:publish --tag=swift-auth:migrations --force

# 3. Run migration (adds id_tenant to Users and Roles)
php artisan migrate

# 4. Publish BeeHive config (if using multi-tenancy)
php artisan vendor:publish --tag=bee-hive:config

# 5. Review config/swift-auth.php — new multi_tenancy block
# 6. Verify cross-tenant queries (add withoutGlobalScope where needed)
# 7. Update exception catch blocks
# 8. Test JSON responses against updated envelope shape
```

---

## Full History

- [CHANGELOG.md](CHANGELOG.md) — complete project history
- [BREAKING_CHANGES.md](BREAKING_CHANGES.md) — migration guide for breaking changes

---

# Release v3.0.0 "Sovereign"

**Release Date:** 2025-01-XX  
**Codename:** Sovereign  
**Type:** Major Release (Breaking Changes)

**SwiftAuth v3.0.0** ("Sovereign") marks a transformative milestone: **complete independence from external authentication dependencies** while delivering comprehensive internationalization support. This release removes `laravel/sanctum` and introduces a native, table-prefix-aware API token system, alongside full English/Spanish localization.

## 🎯 What's New

### 🔐 Native API Authentication System

SwiftAuth now includes a **fully integrated API token system** that respects your table prefix and follows established security patterns.

**Key Features:**

- ✅ **UserToken Model** with SHA-256 hashing (consistent with RememberToken/PasswordResetToken)
- ✅ **Fine-grained Abilities/Scopes** for precise permission control
- ✅ **Token Expiration & Usage Tracking** (`expires_at`, `last_used_at`)
- ✅ **Dedicated Middleware** (`SwiftAuth.AuthenticateWithToken`, `SwiftAuth.CheckTokenAbilities`)
- ✅ **Table Prefix Support** out of the box

**Example:**

```php
use Equidna\SwiftAuth\Classes\Auth\Services\UserTokenService;

$tokenService = app(UserTokenService::class);
$result = $tokenService->createToken(
    user: $user,
    name: 'mobile-app',
    abilities: ['posts:read', 'posts:create'],
    expiresAt: now()->addDays(30),
);

$plainToken = $result['token']; // Store securely!
```

### 🌍 Complete Localization Support

SwiftAuth now speaks **English and Spanish** natively, with flexible architecture for additional languages.

**Included:**

- ✅ 10 Translation Files per language (auth, email, session, user, role)
- ✅ Dynamic Language Switcher UI component (React TypeScript + JavaScript)
- ✅ Session-Based Locale Persistence
- ✅ Inertia.js Integration for seamless frontend translations
- ✅ Fully Localized Email Templates

**Example:**

```php
// PHP/Blade
{{ __('swift-auth::auth.login_title') }}

// JavaScript/TypeScript
import { __ } from './translations';
<h1>{__('auth.login_title')}</h1>
```

### 🛡️ Enhanced Security

**Admin Password Handling:**

- Passwords **never** accepted as CLI arguments (prevents shell history exposure)
- Secure interactive prompting with `secret()` helper
- Auto-generation option for maximum security

**Before:**

```bash
php artisan swift-auth:create-admin "Admin" admin@example.com password123  # ❌ Insecure
```

**After:**

```bash
php artisan swift-auth:create-admin "Admin" admin@example.com
# Prompts: "Enter admin password (leave empty to generate random):"  # ✅ Secure
```

## 📚 Comprehensive Documentation

New guides to help you secure routes and localize your application:

1. **[securing-routes.md](./doc/securing-routes.md)** — 400+ line guide covering session auth, API tokens, hybrid patterns, and testing
2. **[localization.md](./doc/localization.md)** — Complete implementation guide for translations

## ⚠️ Breaking Changes

**This is a MAJOR release with breaking changes.** See [BREAKING_CHANGES.md](./BREAKING_CHANGES.md) for detailed migration instructions.

### Critical Changes:

1. **Sanctum Dependency Removed**
    - `laravel/sanctum` completely removed
    - Native `UserToken` system replaces Sanctum
    - Middleware: `auth:sanctum` → `SwiftAuth.AuthenticateWithToken`

2. **Admin Command Security Update**
    - Password argument removed from CLI
    - Environment variables no longer supported
    - Interactive password entry required

3. **Installation Changes**
    - Translations now published automatically
    - Sanctum migrations no longer published

## 🚀 Migration Quickstart

```bash
# 1. Update dependencies
composer remove laravel/sanctum
composer require equidna/swift-auth:^3.0

# 2. Publish new migrations
php artisan vendor:publish --tag=swift-auth:migrations --force
php artisan migrate

# 3. Update middleware
# Before: Route::middleware('auth:sanctum')
# After:  Route::middleware('SwiftAuth.AuthenticateWithToken')

# 4. Update token creation
# Before: $user->createToken('name', ['ability'])->plainTextToken
# After:  UserTokenService::createToken($user, 'name', ['ability'])
```

**Complete Migration Guide:** [BREAKING_CHANGES.md](./BREAKING_CHANGES.md)

## 🎨 Additional Improvements

- ✅ Fixed 6 code quality issues (SHA-256, debug removal, docs, indexes, config, rate limits)
- ✅ Consolidated database indexes into original migrations
- ✅ Route file consolidation (email verification moved to main routes)
- ✅ Updated architecture diagrams with UserTokenService
- ✅ All frontend components internationalized

## 📊 By The Numbers

- **30+ commits** since v2.0.0
- **2 languages** supported (English, Spanish)
- **10 translation files** per language
- **400+ lines** of route security documentation
- **Zero** external authentication dependencies
- **100%** table prefix compatibility

## 🙏 Acknowledgments

Special thanks to **Gabriel Ruelas** (@gruelas) for architecture and native token system implementation.

## 🔮 What's Next?

- Additional language support (French, German, Portuguese)
- OAuth2/OIDC integration options
- Enhanced MFA capabilities

**Happy Authenticating! 🚀**

_SwiftAuth v3.0.0 "Sovereign" — Building on solid foundations, owning our future._

---

# Release v2.0.0 "Obsidian"

**Release Date:** 2025-12-15

**SwiftAuth v2.0.0** ("Obsidian") is a major release focused on architectural rigidity, strict standards compliance, and developer clarity. It transitions the codebase to a fully strict-typed, Domain-Driven Design (DDD) structure, ensuring higher reliability and better static analysis integration.

While standard features (Login, MFA, Registration) work as expected, the internal structure has changed significantly, which may impact developers who have deeply extended package internals.

## 🚀 Highlights

- **Domain-Driven Structure:** Internal classes are now organized into clear domains (`Auth`, `Notifications`, `Users`).
- **Strict Typing:** Zero-compromise adoption of PHP strict types and return declarations across the board.
- **Documentation Overhaul:** New `/doc` directory with comprehensive diagrams, API references, and deployment guides.
- **Leaner Codebase:** ~350 lines of redundant documentation removed in favor of expressive type signatures.

## ⚠️ Breaking Changes

See [BREAKING_CHANGES.md](BREAKING_CHANGES.md) for the complete migration guide.

- Namespace reorganization in `src/Classes/`.
- Strict type enforcement in method signatures (requires updates to overriding child classes).
- Constructor property promotion adopted in DTOs.

## 📝 Changelog

### Changed

- Moved Notification services to `Classes/Notifications`.
- Moved Auth DTOs and Services to `Classes/Auth`.
- Standardized all file headers with File-Level DocBlocks.
- Removed redundant `@param` and `@return` tags from PHPDoc.

### Added

- New Architectural Diagrams (`doc/architecture-diagrams.md`).
- Full API Documentation (`doc/api-documentation.md`).
- Operational Monitoring Guide (`doc/monitoring.md`).

---

_For full history, see [CHANGELOG.md](CHANGELOG.md)._
