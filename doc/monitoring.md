# Monitoring

> This document describes the observable signals, recommended logging patterns, and monitoring setup for applications using SwiftAuth.

---

## Events as Primary Observability Hooks

SwiftAuth dispatches Laravel events for all authentication state changes. These events are the primary hook for custom monitoring, logging, and alerting in host applications.

| Event                 | When Fired                       | Key Data                                             |
| --------------------- | -------------------------------- | ---------------------------------------------------- |
| `UserLoggedIn`        | Successful login or MFA complete | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `UserLoggedOut`       | Explicit logout                  | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `SessionEvicted`      | Session evicted due to limit     | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |
| `MfaChallengeStarted` | MFA challenge initiated          | `userId`, `sessionId`, `ipAddress`, `driverMetadata` |

**All events** are in `Equidna\SwiftAuth\Classes\Auth\Events\`.

### Registering Event Listeners

In the host application's `EventServiceProvider`:

```php
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedIn;
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedOut;
use Equidna\SwiftAuth\Classes\Auth\Events\SessionEvicted;

protected $listen = [
    UserLoggedIn::class => [
        LogAuthEvent::class,
        NotifySecurityTeam::class,
    ],
    UserLoggedOut::class => [
        LogAuthEvent::class,
    ],
    SessionEvicted::class => [
        LogAuthEvent::class,
    ],
];
```

---

## Recommended Log Channels

SwiftAuth does not impose a specific logging channel. The host application controls log routing. We recommend routing auth events to a dedicated channel.

**Example `config/logging.php` channel:**

```php
'swift-auth' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/swift-auth.log'),
    'level'  => 'info',
    'days'   => 30,
],
```

**Example listener:**

```php
class LogAuthEvent
{
    public function handle(UserLoggedIn $event): void
    {
        Log::channel('swift-auth')->info('User logged in', [
            'user_id'    => $event->userId,
            'session_id' => $event->sessionId,
            'ip_address' => $event->ipAddress,
        ]);
    }
}
```

---

## Config Validation Warnings

`SwiftAuthServiceProvider::boot()` validates the published configuration on every boot. If invalid values are detected (e.g., unsupported frontend adapter, negative rate limit values), it logs warnings via `Log::warning()` to the default log channel.

**Example warning:**

```
[WARNING] SwiftAuth: Invalid frontend adapter "vue" in config. Defaulting to "blade".
```

Watch for these warnings in your application logs after config changes or version upgrades.

---

## Rate Limit Signals

SwiftAuth uses Laravel's `RateLimiter` to enforce login throttling. Rate limiter keys are:

- `swift-auth:login:email:{email}` — per-email login attempts
- `swift-auth:login:ip:{ip}` — per-IP login attempts
- `email-verification:ip:{ip}` — per-IP email verification send
- `email-verification:email:{email}` — per-email verification send

**Monitoring 429 responses:** A spike in `429 Too Many Requests` on login endpoints is a potential brute-force indicator. Route these to your alerting system.

**Example:** Use your web server or APM (Datadog, New Relic, Sentry) to alert on HTTP 429 rates above threshold.

---

## Account Lockout Monitoring

When a user account is locked (`locked_until` is set), `AccountLockoutService` optionally sends a notification via `NotificationService`. This can be wired to an admin alert.

Additionally, you can query locked accounts directly:

```php
use Equidna\SwiftAuth\Models\User;

$lockedUsers = User::where('locked_until', '>', now())->get();
```

Consider a scheduled job or dashboard widget to surface locked accounts.

---

## Session Health Monitoring

The `swift-auth:purge-stale-sessions` command runs on the scheduler and cleans expired session records. If this command stops running (scheduler failure), the `{prefix}Sessions` table can grow unbounded.

**Recommended checks:**

- Monitor that `swift-auth:purge-stale-sessions` completes successfully (check for non-zero exit codes or Laravel scheduler missed-run alerts).
- Set up a database row-count alert on `{prefix}Sessions` if it grows beyond a threshold (e.g., >50,000 rows).

---

## Scheduled Command Monitoring

| Command                           | Frequency  | Failure Impact                                    |
| --------------------------------- | ---------- | ------------------------------------------------- |
| `swift-auth:purge-expired-tokens` | Every hour | Expired tokens remain in DB (minor, non-critical) |
| `swift-auth:purge-stale-sessions` | Daily      | Stale session records accumulate in DB            |

Integrate with a scheduler monitoring tool (e.g., Laravel Pulse, Cronitor, Healthchecks.io):

```php
// In the host app's routes/console.php or AppServiceProvider:
Schedule::command('swift-auth:purge-expired-tokens')
    ->hourly()
    ->pingOnSuccess('https://hc-ping.com/your-uuid-here');
```

---

## APM and Error Tracking

SwiftAuth throws standard Laravel exceptions (from `equidna/toolkit`):

| Exception               | HTTP Status | When Thrown                              |
| ----------------------- | ----------- | ---------------------------------------- |
| `BadRequestException`   | 400         | Invalid input (password reset, role ops) |
| `NotFoundException`     | 404         | User/role/token not found                |
| `UnauthorizedException` | 401         | Authentication failure                   |
| `ForbiddenException`    | 403         | Authorization failure (CanPerformAction) |

These exceptions are handled by Laravel's exception handler and will appear in Sentry, Bugsnag, Flare, or any configured error tracker. No special SwiftAuth adapter is needed.

---

## Health Check Endpoint

SwiftAuth does not ship a dedicated health check endpoint. Consider adding one in the host application:

```php
Route::get('/health/auth', function () {
    return response()->json([
        'db'    => DB::connection()->getPdo() ? 'ok' : 'error',
        'cache' => Cache::store()->getPrefix() ? 'ok' : 'error',
    ]);
})->middleware('SwiftAuth.RequireAuthentication');
```

---

## Metrics Summary

| Signal                           | Source                      | Recommended Alert             |
| -------------------------------- | --------------------------- | ----------------------------- |
| Auth event rate (logins/logouts) | Laravel Events              | Unusual spike or drop         |
| 429 rate on login endpoints      | Web server / APM            | >X per minute in short window |
| Locked accounts count            | DB query                    | >N locked users at once       |
| Sessions table row count         | DB / scheduler              | >50k rows or growing rapidly  |
| Purge command failures           | Scheduler + monitoring tool | Any non-zero exit code        |
| Config validation warnings       | Application log             | Any occurrence after deploy   |
