# Routes Documentation

> All SwiftAuth routes are loaded by `SwiftAuthServiceProvider` from the `routes/` directory. Every route group is wrapped in `SwiftAuth.SecurityHeaders` middleware. The URL prefix defaults to `swift-auth` and is configurable via `config('swift-auth.route_prefix')` (env: `SWIFT_AUTH_ROUTE_PREFIX`).

---

## Route Groups Overview

| Group              | Prefix                      | Auth Required                                         | Notes                             |
| ------------------ | --------------------------- | ----------------------------------------------------- | --------------------------------- |
| Public             | `/{prefix}/`                | None                                                  | Login, logout, locale switch      |
| Password Reset     | `/{prefix}/password/`       | None                                                  | Request, sent confirmation, reset |
| MFA                | `/{prefix}/mfa/`            | Pending MFA session                                   | OTP and WebAuthn verification     |
| WebAuthn           | `/{prefix}/webauthn/`       | Mixed (see below)                                     | Passkey registration and login    |
| Email Verification | `/{prefix}/email/`          | None                                                  | Send and verify email token       |
| Registration       | `/{prefix}/users/`          | None (if enabled)                                     | Public self-registration          |
| Sessions           | `/{prefix}/sessions/`       | `RequireAuthentication`                               | Self-service session management   |
| Admin — Users      | `/{prefix}/users/`          | `RequireAuthentication` + `CanPerformAction:sw-admin` | CRUD for users                    |
| Admin — Roles      | `/{prefix}/roles/`          | `RequireAuthentication` + `CanPerformAction:sw-admin` | CRUD for roles                    |
| Admin — Sessions   | `/{prefix}/admin/sessions/` | `RequireAuthentication` + `CanPerformAction:sw-admin` | Cross-user session management     |

---

## Public Routes (`routes/swift-auth.php`)

| Method | URI                         | Route Name              | Controller / Action            | Description                  |
| ------ | --------------------------- | ----------------------- | ------------------------------ | ---------------------------- |
| GET    | `/{prefix}/login`           | `swift-auth.login.form` | `AuthController@showLoginForm` | Render login page            |
| POST   | `/{prefix}/login`           | `swift-auth.login`      | `AuthController@login`         | Process login (rate-limited) |
| POST   | `/{prefix}/logout`          | `swift-auth.logout`     | `AuthController@logout`        | Destroy session              |
| POST   | `/{prefix}/locale/{locale}` | `swift-auth.locale`     | `AuthController@setLocale`     | Switch UI locale             |

---

## Password Reset Routes (`routes/swift-auth.php`)

| Method | URI                          | Route Name                       | Controller / Action                  | Description                     |
| ------ | ---------------------------- | -------------------------------- | ------------------------------------ | ------------------------------- |
| GET    | `/{prefix}/password`         | `swift-auth.password.form`       | `PasswordController@showRequestForm` | Render reset request form       |
| POST   | `/{prefix}/password`         | `swift-auth.password.send`       | `PasswordController@sendResetLink`   | Send reset email (rate-limited) |
| GET    | `/{prefix}/password/sent`    | `swift-auth.password.sent`       | `PasswordController@showSentPage`    | "Email sent" confirmation page  |
| GET    | `/{prefix}/password/{token}` | `swift-auth.password.reset.form` | `PasswordController@showResetForm`   | Render password reset form      |
| POST   | `/{prefix}/password/reset`   | `swift-auth.password.reset`      | `PasswordController@reset`           | Apply new password              |

---

## MFA Routes (`routes/swift-auth.php`)

| Method | URI                             | Route Name                       | Controller / Action            | Description                       |
| ------ | ------------------------------- | -------------------------------- | ------------------------------ | --------------------------------- |
| POST   | `/{prefix}/mfa/otp/verify`      | `swift-auth.mfa.otp.verify`      | `MfaController@verifyOtp`      | Verify OTP code, finalize login   |
| POST   | `/{prefix}/mfa/webauthn/verify` | `swift-auth.mfa.webauthn.verify` | `MfaController@verifyWebAuthn` | Verify WebAuthn assertion for MFA |

---

## WebAuthn (Passkey) Routes (`routes/swift-auth.php`)

| Method | URI                                   | Route Name                             | Auth     | Controller / Action                  | Description                                 |
| ------ | ------------------------------------- | -------------------------------------- | -------- | ------------------------------------ | ------------------------------------------- |
| POST   | `/{prefix}/webauthn/register/options` | `swift-auth.webauthn.register.options` | Required | `WebAuthnController@registerOptions` | Get attestation options (must be logged in) |
| POST   | `/{prefix}/webauthn/register`         | `swift-auth.webauthn.register`         | Required | `WebAuthnController@register`        | Complete passkey registration               |
| POST   | `/{prefix}/webauthn/login/options`    | `swift-auth.webauthn.login.options`    | None     | `WebAuthnController@loginOptions`    | Get assertion options                       |
| POST   | `/{prefix}/webauthn/login`            | `swift-auth.webauthn.login`            | None     | `WebAuthnController@login`           | Authenticate with passkey                   |

---

## Email Verification Routes (`routes/swift-auth.php`)

| Method | URI                              | Route Name                | Controller / Action                  | Description                            |
| ------ | -------------------------------- | ------------------------- | ------------------------------------ | -------------------------------------- |
| POST   | `/{prefix}/email/send`           | `swift-auth.email.send`   | `EmailVerificationController@send`   | Send verification email (rate-limited) |
| GET    | `/{prefix}/email/verify/{token}` | `swift-auth.email.verify` | `EmailVerificationController@verify` | Verify email token                     |

---

## Registration Routes (`routes/swift-auth.php`)

> Only registered when `config('swift-auth.allow_registration')` is `true`.

| Method | URI                        | Route Name                 | Auth             | Controller / Action     | Description            |
| ------ | -------------------------- | -------------------------- | ---------------- | ----------------------- | ---------------------- |
| GET    | `/{prefix}/users/register` | `swift-auth.register.form` | None             | `UserController@create` | Show registration form |
| POST   | `/{prefix}/users`          | `swift-auth.register`      | None (throttled) | `UserController@store`  | Submit registration    |

---

## User Session Routes (`routes/swift-auth-sessions.php`)

> Require: `SwiftAuth.RequireAuthentication`

| Method | URI                              | Route Name                    | Controller / Action         | Description                        |
| ------ | -------------------------------- | ----------------------------- | --------------------------- | ---------------------------------- |
| GET    | `/{prefix}/sessions`             | `swift-auth.sessions.index`   | `SessionController@index`   | List all sessions for current user |
| DELETE | `/{prefix}/sessions/{sessionId}` | `swift-auth.sessions.destroy` | `SessionController@destroy` | Revoke a specific session          |

---

## Admin — User Management Routes (`routes/swift-auth-users.php`)

> Require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

| Method | URI                              | Route Name                 | Controller / Action      | Description            |
| ------ | -------------------------------- | -------------------------- | ------------------------ | ---------------------- |
| GET    | `/{prefix}/users`                | `swift-auth.users.index`   | `UserController@index`   | List users (paginated) |
| GET    | `/{prefix}/users/create`         | `swift-auth.users.create`  | `UserController@create`  | Show create user form  |
| POST   | `/{prefix}/users`                | `swift-auth.users.store`   | `UserController@store`   | Create new user        |
| GET    | `/{prefix}/users/{id_user}`      | `swift-auth.users.show`    | `UserController@show`    | Show user details      |
| GET    | `/{prefix}/users/{id_user}/edit` | `swift-auth.users.edit`    | `UserController@edit`    | Show edit user form    |
| PUT    | `/{prefix}/users/{id_user}`      | `swift-auth.users.update`  | `UserController@update`  | Update user            |
| DELETE | `/{prefix}/users/{id_user}`      | `swift-auth.users.destroy` | `UserController@destroy` | Delete user            |

---

## Admin — Role Management Routes (`routes/swift-auth-roles.php`)

> Require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

| Method | URI                         | Route Name                 | Controller / Action      | Description            |
| ------ | --------------------------- | -------------------------- | ------------------------ | ---------------------- |
| GET    | `/{prefix}/roles`           | `swift-auth.roles.index`   | `RoleController@index`   | List roles (paginated) |
| GET    | `/{prefix}/roles/create`    | `swift-auth.roles.create`  | `RoleController@create`  | Show create role form  |
| POST   | `/{prefix}/roles`           | `swift-auth.roles.store`   | `RoleController@store`   | Create new role        |
| GET    | `/{prefix}/roles/{id_role}` | `swift-auth.roles.show`    | `RoleController@show`    | Show role details      |
| PUT    | `/{prefix}/roles/{id_role}` | `swift-auth.roles.update`  | `RoleController@update`  | Update role            |
| DELETE | `/{prefix}/roles/{id_role}` | `swift-auth.roles.destroy` | `RoleController@destroy` | Delete role            |

---

## Admin — Session Management Routes (`routes/swift-auth-admin-sessions.php`)

> Require: `SwiftAuth.RequireAuthentication` + `SwiftAuth.CanPerformAction:sw-admin`

| Method | URI                                             | Route Name                             | Controller / Action                 | Description                       |
| ------ | ----------------------------------------------- | -------------------------------------- | ----------------------------------- | --------------------------------- |
| GET    | `/{prefix}/admin/sessions`                      | `swift-auth.admin.sessions.all`        | `AdminSessionController@all`        | List all sessions (all users)     |
| GET    | `/{prefix}/admin/sessions/{userId}`             | `swift-auth.admin.sessions.index`      | `AdminSessionController@index`      | List sessions for a specific user |
| DELETE | `/{prefix}/admin/sessions/{userId}/{sessionId}` | `swift-auth.admin.sessions.destroy`    | `AdminSessionController@destroy`    | Revoke a specific session         |
| DELETE | `/{prefix}/admin/sessions/{userId}`             | `swift-auth.admin.sessions.destroyAll` | `AdminSessionController@destroyAll` | Revoke all sessions for a user    |

---

## Default Route Prefix

The default prefix is `swift-auth`. Every route URI above uses `/{prefix}/` as a placeholder. In a default install, `GET /swift-auth/login` maps to `swift-auth.login.form`.

To change the prefix:

```env
SWIFT_AUTH_ROUTE_PREFIX=auth
```

After changing, re-generate any cached route files:

```bash
php artisan route:clear
php artisan route:cache
```
