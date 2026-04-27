# Artisan Commands

> SwiftAuth registers 8 Artisan commands via `SwiftAuthServiceProvider`. All command signatures use the `swift-auth:` prefix. Two commands are automatically scheduled.

---

## swift-auth:install

**Description:** Interactive installer that configures SwiftAuth, publishes assets, and runs migrations.

**Usage:**

```bash
php artisan swift-auth:install
```

**What it does:**

1. Prompts for key configuration options (`SWIFT_AUTH_FRONTEND`, `SWIFT_AUTH_SUCCESS_URL`, etc.).
2. Publishes `config/swift-auth.php`.
3. Optionally publishes migrations, views, language files, and frontend assets.
4. Optionally runs `php artisan migrate`.

**Notes:** Safe to run on a fresh install. Does not overwrite existing config unless `--force` is specified.

---

## swift-auth:create-admin

**Description:** Creates a new administrator user interactively.

**Usage:**

```bash
php artisan swift-auth:create-admin
```

**What it does:**

1. Prompts for `name` and `email`.
2. Prompts for `password` (hidden input, confirmed).
3. Creates the user record with the `sw-admin` action granted via a default admin role or directly.

**Notes:** Intended for initial setup. Subsequent admin users can be created via the admin UI.

---

## swift-auth:sessions

**Description:** Lists active sessions, optionally filtered by user ID.

**Signature:**

```
swift-auth:sessions {userId?}
```

**Arguments:**

| Argument | Required | Description                                    |
| -------- | -------- | ---------------------------------------------- |
| `userId` | No       | If provided, lists sessions for that user only |

**Usage:**

```bash
# List all sessions
php artisan swift-auth:sessions

# List sessions for user ID 42
php artisan swift-auth:sessions 42
```

**Output:** Table of sessions showing session ID, user ID, IP address, device name, and last activity.

---

## swift-auth:preview-email

**Description:** Previews email templates in the console for development and debugging.

**Signature:**

```
swift-auth:preview-email {template?} {--email=} {--url=}
```

**Arguments:**

| Argument   | Required | Description                                                             |
| ---------- | -------- | ----------------------------------------------------------------------- |
| `template` | No       | Template name to preview (e.g., `password-reset`, `email-verification`) |

**Options:**

| Option    | Description                                  |
| --------- | -------------------------------------------- |
| `--email` | Email address to use as placeholder          |
| `--url`   | URL to use as the action link in the preview |

**Usage:**

```bash
php artisan swift-auth:preview-email password-reset --email=test@example.com --url=https://example.com/reset
```

---

## swift-auth:purge-expired-tokens

**Description:** Removes all expired password reset tokens and email verification tokens from the database.

**Signature:**

```
swift-auth:purge-expired-tokens
```

**Schedule:** Runs automatically **every hour** via the Laravel scheduler.

**Usage:**

```bash
# Manual run
php artisan swift-auth:purge-expired-tokens
```

**What it does:**

- Deletes rows from `{prefix}PasswordResetTokens` where `created_at` is older than `config('swift-auth.password_reset_ttl')`.
- Deletes rows from the users table where `email_verification_sent_at` is expired.

**Notes:** Non-destructive to user data — only cleans up token records.

---

## swift-auth:purge-stale-sessions

**Description:** Deletes session records from the database that have exceeded the absolute or idle session lifetime.

**Signature:**

```
swift-auth:purge-stale-sessions
```

**Schedule:** Runs automatically at the frequency configured in `config('swift-auth.session_cleanup.frequency')` (env: `SWIFT_AUTH_SESSION_CLEANUP_FREQUENCY`, default: `daily`).

**Usage:**

```bash
# Manual run
php artisan swift-auth:purge-stale-sessions
```

**What it does:**

- Deletes rows from `{prefix}Sessions` where `last_activity` indicates the session has expired based on idle and absolute lifetime settings.

---

## swift-auth:revoke-sessions

**Description:** Revokes sessions for a specific user from the command line.

**Signature:**

```
swift-auth:revoke-sessions {userId} {--session=*} {--all} {--remember}
```

**Arguments:**

| Argument | Required | Description    |
| -------- | -------- | -------------- |
| `userId` | Yes      | ID of the user |

**Options:**

| Option        | Description                                       |
| ------------- | ------------------------------------------------- |
| `--session=*` | One or more session UUIDs to revoke (repeatable)  |
| `--all`       | Revoke all sessions for the user                  |
| `--remember`  | Also revoke remember-me tokens when using `--all` |

**Usage:**

```bash
# Revoke all sessions for user 42
php artisan swift-auth:revoke-sessions 42 --all

# Revoke all sessions and remember-me tokens
php artisan swift-auth:revoke-sessions 42 --all --remember

# Revoke specific sessions
php artisan swift-auth:revoke-sessions 42 --session=abc-123 --session=def-456
```

**Notes:** Useful for emergency security responses and automated scripts.

---

## swift-auth:unlock-user

**Description:** Unlocks a user account that has been locked due to repeated failed login attempts.

**Signature:**

```
swift-auth:unlock-user {email}
```

**Arguments:**

| Argument | Required | Description                      |
| -------- | -------- | -------------------------------- |
| `email`  | Yes      | Email address of the locked user |

**Usage:**

```bash
php artisan swift-auth:unlock-user user@example.com
```

**What it does:**

- Sets `locked_until` to `null` on the user record.
- Resets `failed_login_attempts` to `0`.
- Optionally logs/notifies the unlock event.

**Error cases:**

- If no user exists with that email: displays an error message.
- If user is not currently locked: displays an informational message.

---

## Scheduler Summary

| Command                           | Frequency                     |
| --------------------------------- | ----------------------------- |
| `swift-auth:purge-expired-tokens` | Every hour                    |
| `swift-auth:purge-stale-sessions` | Configurable (default: daily) |

The scheduler is registered automatically by `SwiftAuthServiceProvider`. Ensure the Laravel scheduler is set up in your cron or task scheduler — see [Deployment Instructions](deployment-instructions.md#scheduler-setup).
