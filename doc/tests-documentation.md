# Tests Documentation

> SwiftAuth uses PHPUnit 11 with Orchestra Testbench 10 for an in-process Laravel application context. All tests run against an SQLite in-memory database. The test suite is split into **Unit** and **Feature** test suites.

---

## Running Tests

```bash
# Run unit tests (recommended — no full app bootstrap required)
.\vendor\bin\phpunit --testsuite Unit --testdox

# Run all tests (requires Laravel app context; feature tests may need additional setup)
.\vendor\bin\phpunit --testdox

# Run with coverage (requires Xdebug or PCOV)
.\vendor\bin\phpunit --testsuite Unit --coverage-text
```

---

## Test Configuration

**File:** `phpunit.xml`

| Setting                    | Value                 |
| -------------------------- | --------------------- |
| Bootstrap                  | `tests/bootstrap.php` |
| Colors                     | `true`                |
| Fail on warning            | `true`                |
| Fail on risky              | `true`                |
| Strict output during tests | `true`                |
| Cache directory            | `.phpunit.cache`      |
| Source include             | `src/`                |

**Test suites:**

| Suite   | Directory        |
| ------- | ---------------- |
| Unit    | `tests/Unit/`    |
| Feature | `tests/Feature/` |

---

## Test Infrastructure

### `tests/TestCase.php`

Extends `Orchestra\Testbench\TestCase`. Used as the base for all tests.

**Key setup:**

- Registers `SwiftAuthServiceProvider` (and `BirdFlockServiceProvider` if available).
- Calls `BirdFlock::fake()` to mock notification dispatch.
- Calls `Mockery::close()` in `tearDown`.
- Configures SQLite in-memory database via `defineEnvironment`.

### `tests/TestHelpers.php`

Trait providing convenience methods for test setup: creating users, roles, sessions, assigning roles, and generating tokens.

### `tests/TestLogger.php`

PSR-3 logger stub used to inject into services that require a logger, to capture log output in assertions.

### `tests/bootstrap.php`

Minimal PHPUnit bootstrap — sets up the autoloader.

### `tests/Stubs/`

Stub classes used as test doubles. Includes stub implementations of interfaces and service collaborators.

---

## Unit Tests

> 103 tests / 191 assertions as of last full run. All passing.

Located in `tests/Unit/`. Each file focuses on a single class or trait.

| File                                 | Class Under Test                     | What Is Tested                                                                    |
| ------------------------------------ | ------------------------------------ | --------------------------------------------------------------------------------- |
| `AccountLockoutServiceTest.php`      | `AccountLockoutService`              | `isLocked()`, `getRemainingLockoutMinutes()`, lock increment, auto-unlock         |
| `CanPerformActionTest.php`           | `CanPerformAction` (middleware)      | Blocks unauthorized actions, passes authorized requests                           |
| `ChecksRateLimitsTest.php`           | `ChecksRateLimits` (trait)           | `checkRateLimit()`, `hitRateLimit()`, `clearRateLimit()` behavior                 |
| `MfaServiceTest.php`                 | `MfaService`                         | OTP verification, WebAuthn challenge initiation, driver dispatch                  |
| `NotificationServiceTest.php`        | `NotificationService`                | Password reset email dispatch, verification email dispatch, BirdFlock integration |
| `PasswordResetTokenTest.php`         | `PasswordResetToken` (model)         | Token creation, SHA-256 hash comparison, expiry                                   |
| `RequireAuthenticationTest.php`      | `RequireAuthentication` (middleware) | Redirects unauthenticated users, attaches user to request attributes              |
| `RoleTest.php`                       | `Role` (model)                       | `search()` scope, `BelongsToTenant` behavior, actions cast                        |
| `SecurityHeadersTest.php`            | `SecurityHeaders` (middleware)       | Correct security headers appended to response                                     |
| `SessionManagerTest.php`             | `SessionManager`                     | Session creation, eviction (oldest/newest), limit enforcement                     |
| `SwiftSessionAuthPropertiesTest.php` | `SwiftSessionAuth`                   | `check()`, `id()`, `user()`, `userOrFail()` property accessors                    |
| `SwiftSessionAuthTest.php`           | `SwiftSessionAuth`                   | `login()`, `logout()`, session keys, `enforceSessionLimit()`, events              |
| `TokenMetadataValidatorTest.php`     | `TokenMetadataValidator`             | Token validation rules, expiry checks, ability validation                         |
| `UserTest.php`                       | `User` (model)                       | `hasRole()`, `availableActions()`, `BelongsToTenant`, `HasApiTokens`              |
| `UserTokenServiceTest.php`           | `UserTokenService`                   | Token issuance, revocation, ability checks                                        |

---

## Feature Tests

Located in `tests/Feature/`. Feature tests boot the full package service provider and may require a complete Laravel application context. They are not guaranteed to run in isolation without additional setup.

**Current status:** Feature tests may not all pass in standalone package context. They are scoped for future integration test environments where a full app bootstrap is available.

> Agents and CI pipelines should use `--testsuite Unit` for reliable, fast feedback. See [Open Questions & Assumptions](open-questions-and-assumptions.md) for the feature test bootstrap status.

---

## Mocking Strategy

**External dependencies mocked in unit tests:**

| Dependency                   | Mock strategy                                 |
| ---------------------------- | --------------------------------------------- |
| `BirdFlock` (notifications)  | `BirdFlock::fake()` in `TestCase::setUp`      |
| `UserRepositoryInterface`    | Mockery mock                                  |
| `NotificationService`        | Mockery mock where needed                     |
| `SwiftSessionAuth`           | Mockery mock / partial mock                   |
| `SwiftAuth` Facade           | `SwiftAuth::shouldReceive()` pattern          |
| Laravel cache / rate limiter | In-memory array driver (no real cache needed) |
| Database                     | SQLite in-memory via Orchestra Testbench      |

---

## PHPStan

In addition to PHPUnit tests, static analysis runs at **level 7** with Larastan:

```bash
.\vendor\bin\phpstan analyse
```

**Current status:** 0 errors at level 7.

**Configuration:** `phpstan.neon`

---

## Code Style

PHPCS enforces PSR-12 with a 120-character line limit:

```bash
.\vendor\bin\phpcs --standard=ruleset.xml src/
```

**Configuration:** `ruleset.xml`

---

## Known Issues / Deprecations

- 16 PHPUnit deprecation notices (non-blocking) at time of writing. These relate to Orchestra Testbench and PHPUnit internal APIs scheduled for removal in future major versions. They do not affect test validity.
