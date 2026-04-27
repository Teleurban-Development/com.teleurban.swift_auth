# API Documentation

> SwiftAuth does not ship a separate `routes/api.php` file. All package routes use the `web` middleware group (session-aware). However, many endpoints return JSON responses when the request expects JSON (`Accept: application/json`) or when the result is inherently data-only (e.g., session lists, admin operations). This document covers the response contracts and request formats for all endpoints.

---

## Response Format

All responses use `Equidna\Toolkit\Helpers\ResponseHelper`. JSON responses follow this envelope:

**Success:**

```json
{
    "status": "success",
    "message": "Human-readable message.",
    "data": {}
}
```

**Error (4xx/5xx):**

```json
{
    "status": "error",
    "message": "Human-readable error.",
    "data": null
}
```

For Blade (non-JSON) requests, `ResponseHelper` performs redirects with flash session messages instead of returning JSON.

---

## Authentication

### POST `/{prefix}/login`

Authenticates the user and starts a session.

**Route name:** `swift-auth.login`

**Request body (form or JSON):**

| Field      | Type   | Required | Description                          |
| ---------- | ------ | -------- | ------------------------------------ |
| `email`    | string | Yes      | User's email address                 |
| `password` | string | Yes      | User's plain-text password           |
| `remember` | bool   | No       | Whether to issue a remember-me token |

**Rate limits:**

- Per email: `config('swift-auth.login_rate_limits.email.attempts')` (default: 3) per `config('swift-auth.login_rate_limits.email.decay_seconds')` (default: 300s)
- Per IP: `config('swift-auth.login_rate_limits.ip.attempts')` (default: 10) per `config('swift-auth.login_rate_limits.ip.decay_seconds')` (default: 300s)

**Success responses:**

- `200` JSON `{ status: "success", data: { redirect: "/dashboard" } }` — login complete
- `200` JSON `{ status: "pending_mfa", data: { driver: "otp|webauthn" } }` — MFA challenge required

**Error responses:**

- `401` — Invalid credentials
- `403` — Account locked (includes `remaining_minutes` in data)
- `422` — Validation failure
- `429` — Rate limit exceeded

**Events dispatched:**

- `UserLoggedIn` on success
- `MfaChallengeStarted` when MFA is required

---

### POST `/{prefix}/logout`

Terminates the current session.

**Route name:** `swift-auth.logout`

**Request:** No body required (CSRF token via cookie/header).

**Response:**

- `200` / redirect to login form

**Events dispatched:** `UserLoggedOut`

---

### POST `/{prefix}/locale/{locale}`

Sets the application locale for the current session.

**Route name:** `swift-auth.locale`

**Path parameters:**

| Parameter | Description                                                      |
| --------- | ---------------------------------------------------------------- |
| `locale`  | Locale code; must be in `config('swift-auth.supported_locales')` |

**Response:**

- `200` — Locale updated
- `400` — Unsupported locale

---

## MFA Verification

### POST `/{prefix}/mfa/otp/verify`

Verifies the OTP code submitted after a pending MFA challenge.

**Route name:** `swift-auth.mfa.otp.verify`

**Request body:**

| Field | Type   | Required | Description       |
| ----- | ------ | -------- | ----------------- |
| `otp` | string | Yes      | One-time password |

**Response:**

- `200` — Login finalized, session established
- `400` — Missing or invalid OTP
- `401` — Challenge expired or wrong code

---

### POST `/{prefix}/mfa/webauthn/verify`

Verifies the WebAuthn assertion response for MFA.

**Route name:** `swift-auth.mfa.webauthn.verify`

**Request body:** Standard WebAuthn assertion JSON (from navigator.credentials.get).

**Response:**

- `200` — Login finalized
- `401` — Assertion failed

---

## Password Reset

### POST `/{prefix}/password`

Sends a password reset link to the given email address.

**Route name:** `swift-auth.password.send`

**Request body:**

| Field   | Type   | Required | Description      |
| ------- | ------ | -------- | ---------------- |
| `email` | string | Yes      | Registered email |

**Notes:**

- Rate-limited per email and per IP.
- Even when the email is not found, a success response is returned to prevent user enumeration.

**Response:**

- `200` — Email dispatched (or silently ignored for unknown addresses)
- `429` — Rate limit exceeded

---

### POST `/{prefix}/password/reset`

Applies the new password using a valid reset token.

**Route name:** `swift-auth.password.reset`

**Request body:**

| Field                   | Type   | Required | Description                     |
| ----------------------- | ------ | -------- | ------------------------------- |
| `token`                 | string | Yes      | Raw reset token from email link |
| `email`                 | string | Yes      | User's email address            |
| `password`              | string | Yes      | New password                    |
| `password_confirmation` | string | Yes      | Must match `password`           |

**Validation:** Password is validated against `config('swift-auth.password_requirements')`.

**Response:**

- `200` — Password updated successfully
- `400` — Token expired, invalid, or email mismatch
- `422` — Password does not meet requirements

---

## WebAuthn (Passkey)

### POST `/{prefix}/webauthn/login/options`

Returns attestation options for passkey authentication (unauthenticated).

**Route name:** `swift-auth.webauthn.login.options`

**Request body:** May include `email` to pre-populate user handle.

**Response:**

- `200` JSON — WebAuthn PublicKeyCredentialRequestOptions

---

### POST `/{prefix}/webauthn/login`

Authenticates using a passkey credential.

**Route name:** `swift-auth.webauthn.login`

**Request body:** Standard WebAuthn assertion (from `navigator.credentials.get`).

**Response:**

- `200` — Login successful, session established
- `401` — Assertion failed

---

### POST `/{prefix}/webauthn/register/options`

Returns attestation options for registering a new passkey credential.

> **Requires:** `SwiftAuth.RequireAuthentication`

**Route name:** `swift-auth.webauthn.register.options`

**Response:**

- `200` JSON — WebAuthn PublicKeyCredentialCreationOptions
- `401` — Not authenticated

---

### POST `/{prefix}/webauthn/register`

Completes passkey credential registration.

> **Requires:** `SwiftAuth.RequireAuthentication`

**Route name:** `swift-auth.webauthn.register`

**Request body:** Standard WebAuthn attestation (from `navigator.credentials.create`).

**Response:**

- `200` — Credential registered
- `401` — Not authenticated
- `400` — Attestation failed

---

## Email Verification

### POST `/{prefix}/email/send`

Sends an email verification link to the given address.

**Route name:** `swift-auth.email.send`

**Request body:**

| Field   | Type   | Required | Description             |
| ------- | ------ | -------- | ----------------------- |
| `email` | string | Yes      | Email address to verify |

**Rate limits:** Per IP and per email address.

**Response:**

- `200` — Verification email dispatched
- `400` — Invalid email format
- `429` — Rate limit exceeded

---

### GET `/{prefix}/email/verify/{token}`

Verifies the email address using a token from the verification email.

**Route name:** `swift-auth.email.verify`

**Path parameters:**

| Parameter | Description            |
| --------- | ---------------------- |
| `token`   | Raw verification token |

**Response:**

- `200` / redirect — Email verified
- `400` — Token invalid or expired

---

## Sessions (Authenticated User)

### GET `/{prefix}/sessions`

Lists all active sessions for the authenticated user.

> **Requires:** `SwiftAuth.RequireAuthentication`

**Route name:** `swift-auth.sessions.index`

**Response:**

```json
{
    "status": "success",
    "message": "Active sessions loaded.",
    "data": {
        "sessions": [
            {
                "id_session": 1,
                "session_id": "uuid-string",
                "ip_address": "127.0.0.1",
                "user_agent": "Mozilla/5.0 ...",
                "device_name": "Chrome on macOS",
                "last_activity": "2025-01-15T10:30:00Z"
            }
        ]
    }
}
```

---

### DELETE `/{prefix}/sessions/{sessionId}`

Revokes a specific session for the authenticated user.

> **Requires:** `SwiftAuth.RequireAuthentication`

**Route name:** `swift-auth.sessions.destroy`

**Path parameters:**

| Parameter   | Description                   |
| ----------- | ----------------------------- |
| `sessionId` | UUID of the session to revoke |

**Response:**

- `200` — Session revoked
- `404` — Session not found or belongs to another user

---

## Admin: User Management

> All admin endpoints require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

### GET `/{prefix}/users`

Lists users (paginated, supports search).

**Query parameters:**

| Parameter | Type   | Description          |
| --------- | ------ | -------------------- |
| `search`  | string | Optional search term |
| `page`    | int    | Page number          |

**Response:** Paginated user list. Format depends on frontend (JSON or Blade/Inertia).

---

### POST `/{prefix}/users`

Creates a new user.

**Request body:**

| Field      | Type   | Required | Description         |
| ---------- | ------ | -------- | ------------------- |
| `name`     | string | Yes      | User display name   |
| `email`    | string | Yes      | User email (unique) |
| `password` | string | Yes      | Initial password    |
| `roles`    | array  | No       | Array of role IDs   |

---

### PUT `/{prefix}/users/{id_user}`

Updates an existing user.

**Request body:** Partial — only send fields to update.

---

### DELETE `/{prefix}/users/{id_user}`

Deletes a user and all associated sessions, tokens, and remember tokens.

---

## Admin: Role Management

> All admin endpoints require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

### GET `/{prefix}/roles`

Lists roles (paginated, supports search).

---

### POST `/{prefix}/roles`

Creates a new role.

**Request body:**

| Field         | Type   | Required | Description                                   |
| ------------- | ------ | -------- | --------------------------------------------- |
| `name`        | string | Yes      | Role name                                     |
| `description` | string | No       | Role description                              |
| `actions`     | array  | No       | Array of action strings (e.g. `["sw-admin"]`) |

---

### PUT `/{prefix}/roles/{id_role}`

Updates an existing role.

---

### DELETE `/{prefix}/roles/{id_role}`

Deletes a role and disassociates it from all users.

---

## Admin: Session Management

> All admin endpoints require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

### GET `/{prefix}/admin/sessions`

Lists all sessions across all users.

**Response:**

```json
{
    "status": "success",
    "message": "All sessions loaded.",
    "data": { "sessions": [ ... ] }
}
```

---

### GET `/{prefix}/admin/sessions/{userId}`

Lists all sessions for a specific user.

**Response:**

```json
{
    "status": "success",
    "message": "User sessions loaded.",
    "data": { "user_id": 42, "sessions": [ ... ] }
}
```

---

### DELETE `/{prefix}/admin/sessions/{userId}/{sessionId}`

Revokes a specific session for a user.

**Response:**

```json
{
    "status": "success",
    "message": "Session revoked.",
    "data": { "user_id": 42, "session_id": "uuid", "revoked_by": 1 }
}
```

---

### DELETE `/{prefix}/admin/sessions/{userId}`

Revokes **all** sessions for a user.

**Query parameters:**

| Parameter                 | Type | Default | Description                    |
| ------------------------- | ---- | ------- | ------------------------------ |
| `include_remember_tokens` | bool | `false` | Also revoke remember-me tokens |

**Response:**

```json
{
    "status": "success",
    "message": "All sessions revoked.",
    "data": { "user_id": 42, "revoked_count": 3 }
}
```

---

## API Token Authentication

API tokens are issued and managed by the authenticated user. Use `SwiftAuth.AuthenticateWithToken` middleware on routes requiring stateless token auth.

**Issuing a token** (via the Facade, in your application code):

```php
$token = SwiftAuth::user()->createToken('mobile-app', ['posts:read']);
```

**Request header:**

```
Authorization: Bearer {token}
```

**Checking token abilities** (middleware):

```php
Route::middleware(['SwiftAuth.AuthenticateWithToken', 'SwiftAuth.CheckTokenAbilities:posts:write'])
    ->post('/api/posts', [PostController::class, 'store']);
```
