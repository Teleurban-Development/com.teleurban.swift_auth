# Business Logic & Core Processes

> This document describes the key flows, business rules, and decision logic implemented in SwiftAuth. Cross-reference the [Architecture Diagrams](architecture-diagrams.md) for sequence and state views.

---

## Login Flow

**Entry point:** `POST /{prefix}/login` → `AuthController@login`

### Steps

1. **Rate limit check (per email and per IP)**
    - Uses the `ChecksRateLimits` trait.
    - Email key: `config('swift-auth.login_rate_limits.email')` (default: 3 attempts / 300s).
    - IP key: `config('swift-auth.login_rate_limits.ip')` (default: 10 attempts / 300s).
    - Returns `429 Too Many Requests` if exceeded.

2. **User lookup**
    - `User::where('email', $email)->first()`.
    - If not found, returns `401`. A generic message is used to prevent user enumeration.

3. **Account lockout check**
    - `AccountLockoutService::isLocked($user)`.
    - Checks `user->locked_until > now()`.
    - If locked, returns `403` with `remaining_minutes`.

4. **Password verification**
    - `Hash::check($password, $user->password)`.
    - On failure:
        - `AccountLockoutService::recordFailedAttempt($user)` — increments `failed_login_attempts`, sets `locked_until` if threshold (`config('swift-auth.account_lockout.max_attempts')`) is reached.
        - `hitRateLimit` on email + IP keys.
        - Returns `401`.

5. **Post-authentication cleanup**
    - `AccountLockoutService::clearFailedAttempts($user)` — resets `failed_login_attempts` and clears `locked_until`.
    - `clearRateLimit` on email + IP keys.

6. **MFA dispatch (if enabled)**
    - Checks `config('swift-auth.mfa.enabled')` and `user->mfa_enabled` (if present).
    - Calls `SwiftAuth::startMfaChallenge($user, $driver, $ip, $ua)`.
    - Stores `swift_auth_pending_mfa_user_id` and `swift_auth_pending_mfa_driver` in session.
    - Dispatches `MfaChallengeStarted` event.
    - Returns `200` with `status: pending_mfa` — session is NOT fully established yet.

7. **Session initialization**
    - `SwiftAuth::login($user, $ip, $userAgent, $deviceName, $remember)` → `SwiftSessionAuth::login()`.
    - Returns `array{evicted_session_ids: array<int,string>}` — IDs of sessions evicted due to limits.
    - `initializeLoginSession()` stores:
        - `swift_auth_user_id`
        - `swift_auth_session_id` (UUID)
        - `swift_auth_created_at`
        - `swift_auth_last_activity`
        - `swift_auth_absolute_expires_at`
        - `swift_auth_tenant_id` (if multi-tenancy enabled)
    - Dispatches `UserLoggedIn` event.

8. **Remember-me token**
    - If `$remember === true`, `RememberMeService` creates a `RememberToken` record, sets a long-lived cookie.

9. **Response**
    - JSON: `{ status: "success", data: { redirect: config('swift-auth.success_url') } }`
    - Blade: redirect to `success_url`.

---

## Session Lifecycle & Expiry

Sessions are tracked in `{prefix}Sessions` and also in PHP session storage (keyed as `swift_auth_*`).

**Session timeouts:**

| Type     | Config Key                   | Default | Description                                      |
| -------- | ---------------------------- | ------- | ------------------------------------------------ |
| Idle     | `session_lifetimes.idle`     | `900`   | Seconds of inactivity before session expires     |
| Absolute | `session_lifetimes.absolute` | `28800` | Maximum total session age regardless of activity |

On each authenticated request, `SwiftSessionAuth::check()`:

1. Reads `swift_auth_last_activity` from session.
2. If `now() - last_activity > idle_lifetime` → session expired, returns `false`.
3. Reads `swift_auth_absolute_expires_at` from session.
4. If `now() > absolute_expires_at` → session expired, returns `false`.
5. Updates `swift_auth_last_activity = now()`.

**Session limit enforcement:**

Configured via:

- `config('swift-auth.session_limits.max_sessions')` — maximum concurrent sessions per user (0 = unlimited).
- `config('swift-auth.session_limits.eviction')` — `oldest` or `newest`.

On `login()`, `SessionManager::enforceSessionLimit()`:

1. Counts existing sessions for the user.
2. If count >= max, selects sessions to evict (oldest or newest).
3. Deletes evicted `UserSession` records.
4. Dispatches `SessionEvicted` event for each evicted session.
5. Returns array of evicted session UUIDs.

---

## Logout Flow

**Entry point:** `POST /{prefix}/logout` → `AuthController@logout`

1. `SwiftAuth::logout()` → `SwiftSessionAuth::logout()`.
2. Reads `swift_auth_session_id` from session.
3. Deletes the `UserSession` record from the database.
4. Forgets all `swift_auth_*` session keys (including `swift_auth_tenant_id`).
5. Regenerates the PHP session ID (`Session::invalidate()`).
6. Dispatches `UserLoggedOut` event.
7. Response: redirect to login form.

---

## Password Reset Flow

**Entry points:**

- `POST /{prefix}/password` → `PasswordController@sendResetLink`
- `POST /{prefix}/password/reset` → `PasswordController@reset`

### Request Step

1. Rate limit per email and per IP.
2. Look up user by email. If not found, return success silently (prevents enumeration).
3. Generate a cryptographically random token: `Str::random(64)`.
4. Store `hash('sha256', $rawToken)` in `{prefix}PasswordResetTokens` with `expires_at = now() + config('swift-auth.password_reset_ttl')`.
5. Dispatch password reset email via `NotificationService` (→ BirdFlock).

### Reset Step

1. Find `PasswordResetToken` by email.
2. Compare `hash_equals(hash('sha256', $submittedToken), $storedToken)`.
3. Check `expires_at > now()`.
4. Validate new password against `config('swift-auth.password_requirements')` via `PasswordPolicy`.
5. Update `user->password` via `Hash::make($newPassword)`.
6. Delete the `PasswordResetToken` record.
7. Optionally revoke all active sessions.
8. Return success.

**Security note:** `hash_equals` prevents timing attacks. The raw token is never stored.

---

## MFA Flow

**Drivers:** `otp` and `webauthn`.

### Initiation

`SwiftAuth::startMfaChallenge($user, $driver, $ip, $ua)`:

1. Stores `swift_auth_pending_mfa_user_id` in session.
2. Stores `swift_auth_pending_mfa_driver` in session.
3. Dispatches `MfaChallengeStarted` event.
4. Generates driver-specific challenge data (OTP code or WebAuthn assertion options).

### OTP Verification (`POST /{prefix}/mfa/otp/verify`)

1. Read `swift_auth_pending_mfa_user_id` from session.
2. Retrieve user.
3. Delegate to `MfaService::verifyOtp($user, $otp)`.
4. On success: call `SwiftAuth::login(...)` to establish the full session.
5. Clear pending MFA session keys.

### WebAuthn Verification (`POST /{prefix}/mfa/webauthn/verify`)

1. Read `swift_auth_pending_mfa_user_id` from session.
2. Retrieve user.
3. Delegate to `MfaService::verifyWebAuthn($user, $assertionResponse)`.
4. On success: call `SwiftAuth::login(...)` to establish the full session.
5. Clear pending MFA session keys.

---

## Account Lockout

Controlled by `config('swift-auth.account_lockout')`:

| Config Key     | Default | Description                          |
| -------------- | ------- | ------------------------------------ |
| `enabled`      | `true`  | Whether lockout is enforced          |
| `max_attempts` | `5`     | Failed login attempts before lockout |
| `duration`     | `900`   | Lock duration in seconds             |

**Flow on failed login:**

1. `AccountLockoutService::recordFailedAttempt($user)`.
2. Increments `user->failed_login_attempts`.
3. If `failed_login_attempts >= max_attempts`: sets `user->locked_until = now()->addSeconds($duration)`.
4. Saves the user record.
5. Optionally notifies via `NotificationService`.

**Unlocking:**

- Automatically unlocked when `locked_until < now()` — next login attempt will succeed if credentials are correct.
- Manually unlocked via `php artisan swift-auth:unlock-user {email}`.

---

## RBAC (Role-Based Access Control)

### Data Model

- `User` `BelongsToMany` `Role` via `{prefix}UsersRoles` pivot table, using `id_user` and `id_role` foreign keys.
- `Role` has an `actions` JSON column containing an array of action strings (e.g., `["sw-admin", "posts:write"]`).

### Checking Permissions

```php
// Via Facade
SwiftAuth::canPerformAction('sw-admin');        // single action
SwiftAuth::canPerformAction(['sw-admin', 'posts:read']); // any of the listed actions
SwiftAuth::hasRole('administrator');            // check by role name

// Via model
$user->hasRole('administrator');
$user->availableActions();                      // flat array of all actions from all roles
```

### `sw-admin` Action

The built-in `sw-admin` action grants access to all admin routes (user management, role management, admin session management). No other special privilege is hardcoded — all custom permissions are stored in role `actions` arrays.

---

## Multi-Tenancy

See also: [Architecture Diagrams — Multi-Tenancy](architecture-diagrams.md#level-3-component--multi-tenancy).

### Tenant Resolution (per request)

`SwiftAuthTenantResolver` is called by BeeHive at the start of each request. Resolution priority:

1. `X-Tenant-Id` HTTP header
2. `tenant_id` query parameter
3. Session key `swift_auth_tenant_id`
4. Authenticated user's `id_tenant` field
5. `config('swift-auth.multi_tenancy.fallback_tenant_id')` (default: `global`)

When multi-tenancy is disabled, the resolver always returns `global`.

### Tenant Assignment on Model Create

`BelongsToTenant::bootBelongsToTenant()` registers a `creating` Eloquent event listener that:

- Reads `TenantContext::get()`.
- Sets `model->id_tenant` automatically.
- Throws `BeeHiveException` if tenant context is unresolved and no fallback is set.

**Important:** Controllers and services should not manually set `id_tenant` on model creation — BeeHive handles it. Manual setting is only needed when explicitly overriding (e.g., seeding a fallback global record).

### Tenant Isolation on Queries

`TenantScope` (from BeeHive) is applied as a global scope on `User` and `Role` models. All queries automatically include `WHERE id_tenant = ?` using the current tenant context.

---

## Email Verification

Controlled by `config('swift-auth.email_verification')`:

1. After registration (if `email_verification.enabled` is `true`), a token is generated and stored on the user record (`email_verification_token`).
2. `NotificationService` dispatches the verification email.
3. The user clicks the link: `GET /{prefix}/email/verify/{token}`.
4. Token is looked up, validated, and the user's `email_verified_at` is set.
5. The token is cleared from the user record.

---

## Remember-Me Token Flow

1. On `login()` with `$remember = true`, `RememberMeService` generates a random token.
2. Stores `hash('sha256', $rawToken)` in `{prefix}RememberTokens` with `expires_at`.
3. Sets a long-lived cookie with the raw token.
4. On subsequent requests, if no session exists, the cookie token is read, hashed, and looked up.
5. If valid and not expired, a new session is initialized (same as a login).
6. The remember token is rotated on use (old record deleted, new one created).

---

## API Token Flow

1. A user (authenticated via session) creates a token:
    ```php
    $user->createToken('mobile-app', ['posts:read']);
    ```
2. The raw token is returned once. The hashed value (`hash('sha256', $raw)`) is stored in `{prefix}UserTokens`.
3. API requests include the token as `Authorization: Bearer {token}`.
4. `AuthenticateWithToken` middleware reads the header, hashes the value, and looks up the `UserToken` record.
5. `CheckTokenAbilities` middleware validates the required ability against `UserToken->abilities`.
6. `UserToken->last_used_at` is updated on each authenticated request.

---

## Events

All events are dispatched via `Illuminate\Support\Facades\Event::dispatch()`. Host applications can listen to these events in their `EventServiceProvider`.

| Event                 | When Dispatched              | Payload                                              |
| --------------------- | ---------------------------- | ---------------------------------------------------- |
| `UserLoggedIn`        | After successful login       | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `UserLoggedOut`       | After logout                 | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `SessionEvicted`      | When a session is evicted    | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `MfaChallengeStarted` | When MFA challenge initiated | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |

All events are in the `Equidna\SwiftAuth\Classes\Auth\Events\` namespace.

---

## Security Headers

`SecurityHeaders` middleware is applied to all SwiftAuth routes automatically. It injects:

- `X-Frame-Options: DENY` (clickjacking protection)
- `X-Content-Type-Options: nosniff` (MIME sniffing prevention)
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- Additional headers configurable via `config('swift-auth.security_headers')`.

---

## Password Policy

Configured via `config('swift-auth.password_requirements')`. `PasswordPolicy` enforces:

- Minimum length
- Uppercase requirement
- Lowercase requirement
- Numbers requirement
- Special characters requirement

Used on password reset and (optionally) on user creation.
