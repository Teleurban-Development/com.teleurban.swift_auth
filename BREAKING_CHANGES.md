# Breaking Changes & Migration Guide

## v4.0.0 - "Archipelago"

Version 4.0 introduces **multi-tenancy** as a first-class feature via `equidna/bee-hive`, a new Toolkit layer with typed exceptions and `ResponseHelper`, and significant refactoring of core auth services. These changes are breaking for any application that:

- Queries `User` or `Role` without a tenant context.
- Catches exceptions from SwiftAuth by generic type.
- Parses raw SwiftAuth JSON responses.
- Has active sessions from v3.x that relied on the old session key structure.

---

### 1. New Required Dependency: `equidna/bee-hive` ⚠️ CRITICAL

SwiftAuth v4.0.0 **requires `equidna/bee-hive ^2.0`** for multi-tenancy support.

#### What Changed

- `composer.json` now lists `equidna/bee-hive ^2.0` as a required dependency.
- `BeeHiveServiceProvider` is registered automatically by `SwiftAuthServiceProvider`.
- `User` and `Role` models now use `Equidna\BeeHive\Traits\BelongsToTenant`.
- A global `TenantScope` is applied to all `User` and `Role` Eloquent queries.

#### Migration Steps

**Step 1: Update the package**

```bash
composer require equidna/swift-auth:^4.0
```

`equidna/bee-hive` will be pulled automatically.

**Step 2: Publish BeeHive configuration**

```bash
php artisan vendor:publish --tag=bee-hive:config
```

Or let `swift-auth:install` handle it (it now publishes BeeHive config automatically).

**Step 3: Run the new migration**

```bash
php artisan vendor:publish --tag=swift-auth:migrations --force
php artisan migrate
```

This adds `id_tenant` (default `'global'`) to `{prefix}Users` and `{prefix}Roles`.

---

### 2. Tenant-Scoped Queries on User and Role ⚠️ CRITICAL

`User` and `Role` models now carry a global `TenantScope` applied by BeeHive. **All queries are automatically filtered by the current tenant.**

#### What Changed

```php
// v3.x — returns ALL users across all tenants
User::all();

// v4.0 — returns only users belonging to the current tenant
User::all(); // WHERE id_tenant = 'current_tenant'
```

#### When This Affects You

- Admin commands that iterate all users (e.g. seeder, report jobs).
- Tests that create users without setting a tenant context.
- Cross-tenant analytics or admin tooling.

#### Migration Steps

**Option A — Disable multi-tenancy (single-tenant apps)**

In `config/swift-auth.php`:

```php
'multi_tenancy' => [
    'enabled' => false,
    // ...
],
```

When disabled, the tenant resolver always returns `'global'` and the global scope still applies but all records share the `'global'` tenant — effectively no isolation.

**Option B — Remove scope for specific queries**

```php
use Equidna\BeeHive\Scopes\TenantScope;

// Query all users regardless of tenant
User::withoutGlobalScope(TenantScope::class)->get();
```

**Option C — Set tenant context before queries**

```php
use Equidna\BeeHive\TenantContext;

TenantContext::set('my-tenant-id');
User::all(); // Scoped to 'my-tenant-id'
```

---

### 3. New Required Migration ⚠️ REQUIRED

A new migration must be run before the application can boot with v4.0.

#### What Changed

`2026_04_26_000001_add_tenant_columns_to_swift_auth_tables.php` adds:

- `id_tenant` column (default `'global'`, not nullable) to `{prefix}Users`.
- `id_tenant` column (default `'global'`, not nullable) to `{prefix}Roles`.
- Indexes on `id_tenant` for both tables.

#### Migration Steps

```bash
php artisan vendor:publish --tag=swift-auth:migrations --force
php artisan migrate
```

**Data backfill:** All existing rows receive `id_tenant = 'global'` automatically via the column default.

---

### 4. Session Structure Change

`SwiftSessionAuth` now stores an additional key in the PHP session during login.

#### What Changed

| Key                    | v3.x      | v4.0                                         |
|------------------------|-----------|----------------------------------------------|
| `swift_auth_tenant_id` | Not stored | Stored on login, cleared on logout           |

#### Impact

Active sessions from v3.x that are carried over to v4.0 will not have `swift_auth_tenant_id`. On the next request, `SwiftAuthTenantResolver` will fall back to:
1. `X-Tenant-Id` header
2. `tenant_id` query parameter
3. User's `id_tenant` field
4. Config fallback (`'global'`)

**No data loss.** Sessions remain valid; tenant resolution degrades gracefully to the fallback chain.

---

### 5. Typed Exceptions from `Equidna\Toolkit\Exceptions\*`

SwiftAuth now throws **typed exceptions** instead of generic ones.

#### What Changed

| Situation               | v3.x                  | v4.0                                          |
|-------------------------|-----------------------|-----------------------------------------------|
| Invalid input           | `\Exception` / 400    | `BadRequestException` (extends `\Exception`)  |
| Resource not found      | `ModelNotFoundException` | `NotFoundException` (extends `\Exception`) |
| Not authenticated       | Redirect / 401        | `UnauthorizedException`                       |
| Not authorised          | Redirect / 403        | `ForbiddenException`                          |

#### Migration Steps

Update any `try/catch` blocks that catch exceptions thrown by SwiftAuth services:

```php
use Equidna\Toolkit\Exceptions\NotFoundException;
use Equidna\Toolkit\Exceptions\ForbiddenException;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
use Equidna\Toolkit\Exceptions\BadRequestException;

try {
    SwiftAuth::userOrFail();
} catch (NotFoundException $e) {
    // handle not found
} catch (UnauthorizedException $e) {
    // handle unauthenticated
}
```

---

### 6. Normalised JSON Response Shape

Controllers now use `ResponseHelper` which standardises the JSON envelope.

#### What Changed

```json
// v3.x — ad-hoc shapes varied by controller
{ "message": "Login successful", "user": { ... } }

// v4.0 — consistent ResponseHelper envelope
{ "status": "success", "data": { ... }, "message": "..." }
{ "status": "error",   "message": "...", "errors": { ... } }
```

#### Migration Steps

Audit any frontend or API client code that parses SwiftAuth controller JSON responses and update field access to match the new envelope.

---

## v3.0.0 - "Sovereign"

Version 3.0 introduces **major breaking changes** by removing the `laravel/sanctum` dependency and replacing it with a native `UserToken` system. This release also adds comprehensive localization support and improves security for admin user creation.

### 1. Removed Sanctum Dependency ⚠️ CRITICAL

SwiftAuth v3.0.0 **completely removes** `laravel/sanctum` and replaces it with a native API authentication system.

#### What Changed

-   `composer.json` no longer lists `laravel/sanctum` as a dependency
-   Sanctum migrations are no longer published during `swift-auth:install`
-   New `UserToken` model, service, and migration replace Sanctum's `personal_access_tokens`
-   New middleware: `SwiftAuth.AuthenticateWithToken` and `SwiftAuth.CheckTokenAbilities`

#### Why This Change

1. **Table Prefix Compatibility:** Sanctum doesn't respect SwiftAuth's configurable table prefix
2. **Pattern Consistency:** Native tokens follow SwiftAuth's SHA-256 hashing patterns
3. **Reduced Dependencies:** Eliminates external dependency for core functionality
4. **Full Control:** Complete ownership of API authentication logic

#### Migration Steps

**Step 1: Update Dependencies**

```bash
composer remove laravel/sanctum
composer require equidna/swift-auth:^3.0
```

**Step 2: Publish New Migrations**

```bash
php artisan vendor:publish --tag=swift-auth:migrations --force
php artisan migrate
```

**Step 3: Update Middleware**

```php
// Before (v2.x)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});

// After (v3.0)
Route::middleware('SwiftAuth.AuthenticateWithToken')->group(function () {
    Route::get('/api/posts', [PostController::class, 'index']);
});
```

**Step 4: Update Token Creation**

```php
// Before (Sanctum)
$token = $user->createToken('api-token', ['posts:read'])->plainTextToken;

// After (SwiftAuth)
use Equidna\SwiftAuth\Classes\Auth\Services\UserTokenService;

$tokenService = app(UserTokenService::class);
$result = $tokenService->createToken(
    user: $user,
    name: 'api-token',
    abilities: ['posts:read'],
    expiresAt: now()->addDays(30),
);
$token = $result['token']; // Store securely!
```

**Step 5: Update Ability Checks**

```php
// Before
if ($request->user()->tokenCan('posts:create')) { ... }

// After - Option 1: Middleware
Route::middleware([
    'SwiftAuth.AuthenticateWithToken',
    'SwiftAuth.CheckTokenAbilities:posts:create',
])->post('/api/posts', [PostController::class, 'store']);

// After - Option 2: Manual
$token = $request->attributes->get('user_token');
if ($token && $token->can('posts:create')) { ... }
```

**Step 6: Update Tests**

```php
// Before
use Laravel\Sanctum\Sanctum;
Sanctum::actingAs($user, ['posts:read']);

// After
$tokenService = app(UserTokenService::class);
$result = $tokenService->createToken($user, 'test-token', ['posts:read']);
$this->withHeader('Authorization', 'Bearer ' . $result['token'])
    ->getJson('/api/posts');
```

**Step 7: Migrate Existing Tokens (Optional)**

If you have existing Sanctum tokens and want to preserve them:

```php
// Create migration to copy data
DB::table($prefix . 'UserTokens')->insert(
    DB::table('personal_access_tokens')
        ->select([
            'tokenable_id as id_user',
            'name',
            'token as hashed_token',
            'abilities',
            'last_used_at',
            'expires_at',
            'created_at',
            'updated_at',
        ])
        ->get()
        ->toArray()
);
```

### 2. Admin User Creation Security Enhancement

The `swift-auth:create-admin` command no longer accepts passwords as CLI arguments for security reasons.

#### What Changed

**Before (v2.x):**

```bash
# Insecure - password visible in shell history
php artisan swift-auth:create-admin "Admin" admin@example.com password123

# Or via environment variables
SWIFT_ADMIN_NAME="Admin" SWIFT_ADMIN_EMAIL="admin@example.com" php artisan swift-auth:create-admin
```

**After (v3.0):**

```bash
# Password MUST be entered interactively
php artisan swift-auth:create-admin "Admin" admin@example.com
# Command prompts: "Enter admin password (leave empty to generate random):"
```

#### Why This Change

1. **Security:** Passwords in CLI arguments are visible in shell history and process lists
2. **Best Practice:** Interactive password entry prevents accidental exposure
3. **Convenience:** Auto-generation option for secure random passwords

#### Migration Actions

-   Remove `SWIFT_ADMIN_NAME` and `SWIFT_ADMIN_EMAIL` from `.env` files
-   Update deployment scripts to use interactive prompts or expect auto-generated passwords
-   Document generated passwords securely when using auto-generation

### 3. Installation Command Changes

The `swift-auth:install` command now publishes translation files automatically.

#### What Changed

**Before (v2.x):**

-   Did not publish translation files
-   Published Sanctum migrations separately

**After (v3.0):**

-   Automatically publishes `swift-auth:lang` translations (10 files)
-   No longer publishes Sanctum migrations
-   Groups all SwiftAuth migrations before running `migrate`

#### Migration Actions

No action required unless you have custom translation files that might conflict.

### 4. Route File Consolidation

Email verification routes have been consolidated into the main route file.

#### What Changed

**Before (v2.x):**

-   Separate `routes/swift-auth-email-verification.php` file

**After (v3.0):**

-   All routes in `routes/swift-auth.php`
-   Email verification routes: `POST /email/send`, `GET /email/verify/{token}`

#### Migration Actions

None required if using default package routes. Check for conflicts if you've customized routes.

### 5. Complete API Migration Summary

| Feature        | v2.x (Sanctum)         | v3.0 (UserToken)                           |
| -------------- | ---------------------- | ------------------------------------------ |
| Dependency     | `laravel/sanctum`      | Native SwiftAuth                           |
| Middleware     | `auth:sanctum`         | `SwiftAuth.AuthenticateWithToken`          |
| Token Creation | `$user->createToken()` | `UserTokenService::createToken()`          |
| Ability Check  | `$user->tokenCan()`    | `SwiftAuth.CheckTokenAbilities` middleware |
| Revocation     | `$token->delete()`     | `UserTokenService::revokeToken()`          |
| Table Prefix   | Not supported          | Fully supported via config                 |
| Expiration     | `expires_at`           | `expires_at` + `isExpired()` method        |
| Hashing        | SHA-256                | SHA-256 (compatible)                       |

### 6. Documentation Resources

-   **Route Security:** `doc/securing-routes.md` - Comprehensive guide with examples
-   **Localization:** `doc/localization.md` - Translation system guide
-   **API Docs:** `doc/api-documentation.md` - Updated with UserToken endpoints
-   **README:** Updated with security quick reference

---

## v2.0.0 - "Obsidian"

Version 2.0 introduces strict architectural standards and a Domain-Driven Design (DDD) reorganization to improve maintainability and type safety.

### 1. Class Relocations (Domain Structure)

Files within `src/Classes/` have been organized into strict domains. If you were importing classes directly from `Equidna\SwiftAuth\Classes`, you may need to update your imports.

| Old Namespace / Path                                | New Namespace / Path                                             |
| :-------------------------------------------------- | :--------------------------------------------------------------- |
| `Equidna\SwiftAuth\Classes\NotificationService`     | `Equidna\SwiftAuth\Classes\Notifications\NotificationService`    |
| `Equidna\SwiftAuth\Classes\RememberMeService`       | `Equidna\SwiftAuth\Classes\Auth\Services\RememberMeService`      |
| `Equidna\SwiftAuth\Classes\RememberToken`           | `Equidna\SwiftAuth\Classes\Auth\DTO\RememberToken`               |
| `Equidna\SwiftAuth\Classes\NotificationResult`      | `Equidna\SwiftAuth\Classes\Notifications\DTO\NotificationResult` |
| `Equidna\SwiftAuth\Classes\Traits\ChecksRateLimits` | `Equidna\SwiftAuth\Classes\Auth\Traits\ChecksRateLimits`         |

#### Migration Action:

Search and replace namespace imports in your application code if you have extended or used these internal classes directly.

### 2. Strict Type Enforcement

We have enforced native PHP return types and parameter types across the codebase to reduce reliance on PHPDoc.

-   **Before:**

    ```php
    /**
     * @return string
     */
    public function getToken() { ... }
    ```

-   **After:**
    ```php
    public function getToken(): string { ... }
    ```

#### Migration Action:

If you have **extended** any SwiftAuth classes and overridden methods, you **must** update your method signatures to match the new strict types. Failure to do so will result in a fatal PHP error (`Declaration of Child::method() must be compatible with Parent::method()`).

### 3. Constructor Property Promotion

Many DTOs and Services now use Constructor Property Promotion.

-   **Impact:** If you were using reflection or relying on specific internal property existence before the constructor ran, behavior might slightly differ, though public API surfaces remain largely compatible.

### 4. Event Constructors

Auth events (`UserLoggedIn`, `SessionEvicted`, etc.) now enforce strict types in their constructors.

-   `userId` is strictly `int|string|null`.
-   `driverMetadata` is strictly `array`.

#### Migration Action:

Ensure any manual instantiation of these events passes the correct strictly typed arguments.
