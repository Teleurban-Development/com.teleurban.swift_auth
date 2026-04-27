# Architecture Diagrams

> This document contains Mermaid diagrams describing the SwiftAuth system architecture at three levels of detail: System Context, Container, and Component.

---

## Level 1: System Context

Describes how SwiftAuth fits within a host Laravel application and its external interactions.

```mermaid
C4Context
    title SwiftAuth — System Context

    Person(user, "End User", "Authenticates via web browser or API client")
    Person(admin, "Administrator", "Manages users, roles, and sessions")

    System(hostApp, "Host Laravel Application", "The Laravel 11/12 app that installs SwiftAuth as a package")
    System_Ext(beeHive, "BeeHive (equidna/bee-hive)", "Multi-tenancy: TenantContext, TenantScope, BelongsToTenant")
    System_Ext(birdFlock, "BirdFlock (equidna/bird-flock)", "Notification and email dispatch bus")
    System_Ext(webAuthn, "Laragear WebAuthn", "WebAuthn / Passkey credential management")
    System_Ext(sanctum, "Laravel Sanctum", "API token issuance and validation")
    System_Ext(mailer, "Mail Service", "SMTP / Mailgun / SES — sends password reset and verification emails")

    Rel(user, hostApp, "Submits login, MFA, password reset", "HTTPS")
    Rel(admin, hostApp, "Manages users and sessions", "HTTPS")
    Rel(hostApp, beeHive, "Resolves tenant context", "PHP in-process")
    Rel(hostApp, birdFlock, "Dispatches email notifications", "PHP in-process")
    Rel(hostApp, webAuthn, "WebAuthn challenge/assertion", "PHP in-process")
    Rel(hostApp, sanctum, "Issues and validates API tokens", "PHP in-process")
    Rel(hostApp, mailer, "Delivers emails", "SMTP/API")
```

---

## Level 2: Container

Describes the internal packages and the Laravel application layer.

```mermaid
C4Container
    title SwiftAuth — Container View

    Container(routes, "Routes", "PHP", "Loads route groups from routes/*.php with prefix and middleware")
    Container(controllers, "HTTP Controllers", "PHP / Laravel", "Handles HTTP request/response; delegates to services")
    Container(facade, "SwiftAuth Facade", "PHP", "Public API: login, logout, check, canPerformAction, sessions")
    Container(service, "SwiftSessionAuth", "PHP Service", "Core auth orchestrator: login, logout, session keys, MFA, limits")
    Container(services, "Auth Services", "PHP", "AccountLockoutService, MfaService, SessionManager, PasswordPolicy, RememberMeService, UserTokenService")
    Container(middleware, "Middleware", "PHP / Laravel", "RequireAuthentication, CanPerformAction, SecurityHeaders, AuthenticateWithToken, CheckTokenAbilities, ShareInertiaData")
    Container(models, "Eloquent Models", "PHP / Eloquent", "User, Role, UserSession, RememberToken, PasswordResetToken, UserToken — with BelongsToTenant trait")
    Container(notifications, "NotificationService", "PHP", "Wraps BirdFlock to dispatch password reset, email verification, lockout notifications")
    ContainerDb(db, "Database", "MySQL / PostgreSQL / SQLite", "Users, Roles, Sessions, Tokens — prefixed tables")
    Container(cache, "Cache", "Laravel Cache", "Rate limiter, roles cache (tenant-keyed)")

    Rel(routes, controllers, "Dispatches HTTP requests to")
    Rel(controllers, facade, "Calls")
    Rel(facade, service, "Proxies to SwiftSessionAuth singleton")
    Rel(service, services, "Delegates to")
    Rel(service, models, "Reads/writes")
    Rel(services, models, "Reads/writes")
    Rel(services, notifications, "Sends emails via")
    Rel(controllers, middleware, "Protected by")
    Rel(models, db, "Persists to")
    Rel(service, cache, "Stores rate limits and roles")
```

---

## Level 3: Component — Login Flow

Describes the detailed component interactions during a login request.

```mermaid
sequenceDiagram
    participant Browser
    participant AuthController
    participant SwiftSessionAuth
    participant AccountLockoutService
    participant SessionManager
    participant User (Model)
    participant EventDispatcher

    Browser->>AuthController: POST /swift-auth/login {email, password, remember}
    AuthController->>AuthController: Check rate limits (email + IP)
    AuthController->>User (Model): Find by email
    User (Model)-->>AuthController: User instance or null

    alt User not found
        AuthController-->>Browser: 401 Unauthorized
    end

    AuthController->>AccountLockoutService: isLocked(user)
    alt User is locked
        AuthController-->>Browser: 403 Forbidden {remaining_minutes}
    end

    AuthController->>AuthController: Hash::check(password, user.password)
    alt Password invalid
        AuthController->>AccountLockoutService: recordFailedAttempt(user)
        AuthController->>AuthController: hitRateLimit(email, ip)
        AuthController-->>Browser: 401 Unauthorized
    end

    AuthController->>AccountLockoutService: clearFailedAttempts(user)
    AuthController->>SwiftSessionAuth: login(user, ip, ua, device, remember)
    SwiftSessionAuth->>SessionManager: enforceSessionLimit(user, sessionUid)
    SessionManager-->>SwiftSessionAuth: evicted_session_ids[]
    SwiftSessionAuth->>SwiftSessionAuth: initializeLoginSession()
    SwiftSessionAuth->>EventDispatcher: dispatch(UserLoggedIn)

    alt MFA enabled
        SwiftSessionAuth->>SwiftSessionAuth: startMfaChallenge(user, driver)
        SwiftSessionAuth->>EventDispatcher: dispatch(MfaChallengeStarted)
        AuthController-->>Browser: 200 {status: pending_mfa, driver}
    else MFA not required
        AuthController-->>Browser: 200 {status: success, redirect}
    end
```

---

## Level 3: Component — Session Lifecycle

```mermaid
stateDiagram-v2
    [*] --> Unauthenticated

    Unauthenticated --> PendingMFA : login() + MFA enabled
    Unauthenticated --> Authenticated : login() + MFA not required

    PendingMFA --> Authenticated : MFA challenge verified
    PendingMFA --> Unauthenticated : Challenge expired or failed

    Authenticated --> Unauthenticated : logout()
    Authenticated --> Unauthenticated : Idle timeout exceeded
    Authenticated --> Unauthenticated : Absolute timeout exceeded
    Authenticated --> Unauthenticated : Session revoked by user or admin
    Authenticated --> Unauthenticated : Session evicted (session limit enforced)
```

---

## Level 3: Component — Multi-Tenancy

```mermaid
flowchart TD
    Request([HTTP Request]) --> Resolver[SwiftAuthTenantResolver]
    Resolver -->|1. X-Tenant-Id header| TenantSet[TenantContext::set]
    Resolver -->|2. ?tenant_id query| TenantSet
    Resolver -->|3. Session swift_auth_tenant_id| TenantSet
    Resolver -->|4. Authenticated user.id_tenant| TenantSet
    Resolver -->|5. Fallback: 'global'| TenantSet

    TenantSet --> BeeHive[BeeHive TenantContext]
    BeeHive --> TenantScope[Global TenantScope on User + Role models]
    BeeHive --> BelongsToTenant[BelongsToTenant auto-assigns id_tenant on creating]

    TenantScope --> DB[(Database: filtered by id_tenant)]
    BelongsToTenant --> DB
```

---

## Key Design Decisions

| Decision                          | Rationale                                                                                    |
| --------------------------------- | -------------------------------------------------------------------------------------------- |
| Single Facade (`SwiftAuth`)       | Provides a stable, discoverable public API without exposing internals                        |
| `SwiftSessionAuth` as singleton   | Reuses session/request state within a single request lifecycle                               |
| `BelongsToTenant` via BeeHive     | Tenant isolation is enforced at the Eloquent layer, not in controllers                       |
| Config-driven table/route prefix  | Avoids naming collisions in host apps with existing tables or routes                         |
| `SelectiveRender` trait           | Keeps controllers frontend-agnostic; supports Blade and Inertia equally                      |
| SHA-256 tokens (not encrypted)    | Reset and verification tokens stored as `hash('sha256', $raw)` — fast, one-way, no IV needed |
| Events for all auth state changes | Allows host apps to react to login, logout, eviction, and MFA events                         |
