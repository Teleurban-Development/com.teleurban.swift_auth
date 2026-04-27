# Open Questions & Assumptions

> This document tracks unresolved design questions, explicit assumptions made during development, and areas that require clarification from integrating teams.

---

## Open Questions

### OQ-1: Feature Test Bootstrap

**Status:** Unresolved

**Issue:** Feature tests in `tests/Feature/` require a full Laravel application bootstrap (service providers, config, routes, middleware, database). The current `tests/bootstrap.php` and `TestCase.php` use Orchestra Testbench, which provides a minimal Laravel environment. Some feature test scenarios (e.g., Inertia rendering, BeeHive tenant resolution end-to-end) may require a more complete setup.

**Impact:** Feature tests are not guaranteed to pass in CI without a dedicated Laravel app context. Agents and contributors should avoid modifying feature tests unless the bootstrap is verified.

**Recommendation:** Consider adding a dedicated Laravel integration test app under `tests/laravel-app/` or using a Docker-based CI environment with a real database.

---

### OQ-2: Pre-Existing Documentation Files

**Status:** Unresolved

**Issue:** The files `doc/localization.md` and `doc/securing-routes.md` pre-existed before the documentation generation session and were not regenerated. Their accuracy relative to the current codebase has not been verified.

**Recommendation:** Review both files and bring them in line with the current codebase when the next documentation pass is scheduled.

---

### OQ-3: Queue Driver Assumptions for Notifications

**Status:** Assumption — not enforced

**Issue:** `NotificationService` wraps BirdFlock (`equidna/bird-flock`). Whether notifications are dispatched synchronously or via a queue depends entirely on the host application's queue driver configuration.

**Impact:** In local/test environments using the `sync` driver, all notifications are dispatched inline. In production with `redis` or `database` drivers, notifications are queued. SwiftAuth does not document or enforce this behavior.

**Recommendation:** Document in host app onboarding that the queue driver should be set to `sync` for simple deployments or `redis` for production. Consider whether `swift-auth:install` should prompt for this.

---

### OQ-4: WebAuthn Relying Party Configuration

**Status:** Assumption — not enforced

**Issue:** `laragear/webauthn` requires `APP_URL` and `APP_NAME` to be set in the host application's environment. SwiftAuth does not validate these values during boot.

**Impact:** WebAuthn registration and assertion will fail silently or with cryptic errors if these values are missing or incorrect (e.g., wrong domain).

**Recommendation:** Add a config validation check in `SwiftAuthServiceProvider::boot()` that warns if `app.url` contains `localhost` and WebAuthn is enabled, or if MFA driver is `webauthn` but `APP_URL` is missing.

---

### OQ-5: Database Driver Compatibility

**Status:** Assumption — partially tested

**Issue:** All package migrations are designed for any Laravel-supported database driver (MySQL, PostgreSQL, SQLite, SQL Server). However, unit and feature tests run only on SQLite in-memory.

**Impact:** JSON column handling (`Role.actions`), index naming, and migration syntax may behave differently on MySQL/PostgreSQL in edge cases.

**Recommendation:** Add CI matrix with at least MySQL/PostgreSQL. Consider using `$table->json('actions')->nullable()` where supported, with a fallback `$table->text('actions')` cast.

---

### OQ-6: `sw-admin` Action String Conflicts

**Status:** Assumption — documented by convention

**Issue:** The built-in `sw-admin` action string is used to protect all SwiftAuth admin routes. If a host application defines custom roles that happen to include an action named `sw-admin` for unrelated purposes, users with that action will gain unintended access to SwiftAuth admin routes.

**Recommendation:** Document the reserved action string clearly. Consider namespacing it more explicitly (e.g., `swift-auth:admin`) in a future breaking-change release.

---

### OQ-7: Multi-Tenancy with Session-Based Tenant Key

**Status:** Design decision — may need revisitation

**Issue:** The `SwiftAuthTenantResolver` falls back to reading `swift_auth_tenant_id` from the PHP session. If a user's session is hijacked or replayed from a different context, the tenant could be incorrectly resolved.

**Impact:** Potential cross-tenant data leak if session security is compromised.

**Recommendation:** Ensure `config('swift-auth.security_headers')` is enabled, `session.secure` is `true` in production, and HTTPS is enforced. Consider whether the session-based resolver step should be optional or disabled in high-security tenancy setups.

---

### OQ-8: Remember-Me Token Rotation Strategy

**Status:** Assumption — standard rolling token approach

**Issue:** Remember-me tokens are rotated on use (old record deleted, new one created). This is the standard pattern but requires the client cookie to always be up to date. If the same cookie is sent twice (e.g., due to network retry or browser back-forward cache), the second request will fail authentication silently.

**Recommendation:** Document this behavior for host app teams. Consider adding a grace period (short window where the old token remains valid) if back-forward cache issues are reported.

---

### OQ-9: Absolute Session Timeout Edge Cases

**Status:** Assumption — not fully verified

**Issue:** `swift_auth_absolute_expires_at` is set once at login and not updated. If the server clock changes (NTP resync, DST) during a session, the absolute timeout may expire earlier or later than expected.

**Recommendation:** Verify behavior in environments with frequent NTP corrections. Consider using a monotonic timestamp or a session creation + max-age check instead of an absolute timestamp.

---

### OQ-10: Artisan `swift-auth:install` Idempotency

**Status:** Unverified

**Issue:** The interactive `swift-auth:install` command publishes config, migrations, and views. If run a second time on an already-configured project, it may overwrite customized files without warning.

**Recommendation:** Add a check in the installer that detects existing published files and prompts before overwriting, or uses `--force` flag semantics explicitly.

---

## Assumptions

| ID   | Assumption                                                                                                        |
| ---- | ----------------------------------------------------------------------------------------------------------------- |
| A-01 | Host applications are responsible for running `php artisan migrate` after publishing migrations.                  |
| A-02 | The scheduler (`php artisan schedule:run`) is configured to run every minute in production.                       |
| A-03 | The host app configures `session.secure = true` and HTTPS in production environments.                             |
| A-04 | Multi-tenancy is opt-in; when `multi_tenancy.enabled = false`, all data is scoped to tenant `global`.             |
| A-05 | The `equidna/toolkit` `ResponseHelper` is available; it is a required dependency via `composer.json`.             |
| A-06 | BirdFlock dispatches notifications; the host app's `MAIL_*` environment variables are correctly configured.       |
| A-07 | `config('swift-auth.table_prefix')` is set before any migration runs; changing it after migrations break queries. |
| A-08 | The `default_role_id` in config corresponds to a role that exists in the database at application boot time.       |
| A-09 | All SwiftAuth admin functionality requires the `sw-admin` action — no separate superuser model is used.           |
| A-10 | The `frontend` config value (`blade`, `typescript`, `javascript`) does not change at runtime.                     |
