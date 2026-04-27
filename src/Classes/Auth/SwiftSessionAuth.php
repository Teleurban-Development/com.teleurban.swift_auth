<?php

/**
 * Implements SwiftAuth's session-backed authentication service helpers.
 */

namespace Equidna\SwiftAuth\Classes\Auth;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Classes\Auth\Events\SessionEvicted;
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedIn;
use Equidna\SwiftAuth\Classes\Auth\Events\UserLoggedOut;
use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\SwiftAuth\Classes\Auth\Services\RememberMeService;
use Equidna\SwiftAuth\Classes\Auth\Services\SessionManager;
use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Models\UserSession;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\Cookie;
use SessionHandlerInterface;

/**
 * Handles login, logout, session checks, and remember-me flows using Laravel's session storage.
 */
class SwiftSessionAuth
{
    protected string $sessionKey = 'swift_auth_user_id';
    protected string $sessionUidKey = 'swift_auth_session_id';
    protected string $createdAtKey = 'swift_auth_created_at';
    protected string $lastActivityKey = 'swift_auth_last_activity';
    protected string $absoluteExpiryKey = 'swift_auth_absolute_expires_at';
    protected string $pendingMfaUserKey = 'swift_auth_pending_mfa_user_id';
    protected string $pendingMfaDriverKey = 'swift_auth_pending_mfa_driver';
    protected string $tenantSessionKey = 'swift_auth_tenant_id';
    protected string $rememberCookieName = 'swift_auth_remember';

    private ?User $cachedUser = null;

    public function __construct(
        protected Session $session,
        protected UserRepositoryInterface $userRepository,
        protected Dispatcher $events,
        protected RememberMeService $rememberMeService,
        protected SessionManager $sessionManager,
        protected MfaService $mfaService,
    ) {
        $this->tenantSessionKey = (string) config('swift-auth.multi_tenancy.session_key', 'swift_auth_tenant_id');
    }

    /**
     * Logs in a user by storing their ID in the session.
     *
     * @return array{evicted_session_ids: array<int, string>}
     */
    public function login(
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceName = null,
        bool $remember = false,
    ): array {
        $metadata = $this->extractAgentMetadata($userAgent);
        $now = CarbonImmutable::now();

        $sessionId = $this->initializeLoginSession($user, $now);

        $evicted = $this->recordAndEnforceSessionLimits(
            user: $user,
            sessionId: $sessionId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            deviceName: $deviceName,
            metadata: $metadata,
            now: $now,
        );

        $this->queueRememberTokenIfRequested($remember, $user, $ipAddress, $userAgent, $deviceName, $metadata);
        $this->dispatchLoginEvent($user, $sessionId, $ipAddress);

        return [
            'evicted_session_ids' => $evicted,
        ];
    }

    private function initializeLoginSession(User $user, CarbonImmutable $now): string
    {
        $this->session->regenerate(true);
        $this->cachedUser = $user;

        $sessionId = $this->session->getId();
        $absoluteExpiry = $this->getAbsoluteExpiry($now);

        $this->session->forget($this->pendingMfaUserKey);
        $this->session->forget($this->pendingMfaDriverKey);

        $this->session->put($this->sessionKey, $user->getKey());
        $this->session->put($this->sessionUidKey, $sessionId);
        $this->session->put($this->createdAtKey, $now->toIso8601String());
        if (isset($user->id_tenant) && is_scalar($user->id_tenant)) {
            $this->session->put($this->tenantSessionKey, (string) $user->id_tenant);
        }

        if ($absoluteExpiry !== null) {
            $this->session->put($this->absoluteExpiryKey, $absoluteExpiry->toIso8601String());
        }

        return $sessionId;
    }

    /**
     * @param  array{platform:null|string,browser:null|string} $metadata
     * @return array<int, string>
     */
    private function recordAndEnforceSessionLimits(
        User $user,
        string $sessionId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        array $metadata,
        CarbonImmutable $now,
    ): array {
        $this->sessionManager->record(
            user: $user,
            sessionId: $sessionId,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            deviceName: $deviceName,
            platform: $metadata['platform'],
            browser: $metadata['browser'],
            lastActivity: $now,
        );

        return $this->sessionManager->enforceLimits(
            user: $user,
            currentSessionId: $sessionId,
        );
    }

    /**
     * @param array{platform:null|string,browser:null|string} $metadata
     */
    private function queueRememberTokenIfRequested(
        bool $remember,
        User $user,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        array $metadata,
    ): void {
        if (!$remember) {
            return;
        }

        $this->rememberMeService->queueToken(
            user: $user,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            deviceName: $deviceName,
            platform: $metadata['platform'],
            browser: $metadata['browser'],
        );
    }

    private function dispatchLoginEvent(User $user, string $sessionId, ?string $ipAddress): void
    {
        $this->dispatchEvent(new UserLoggedIn(
            $user->getKey(),
            $sessionId,
            $ipAddress,
            $this->getDriverMetadata()
        ));
    }

    /**
     * Logs out the user by removing their ID from the session.
     */
    public function logout(): void
    {
        $sessionId = (string) $this->session->get($this->sessionUidKey);
        $rememberValue = Cookie::get($this->rememberCookieName);
        $userId = $this->session->get($this->sessionKey);

        $this->cachedUser = null;

        $this->session->forget($this->sessionKey);
        $this->session->forget($this->sessionUidKey);
        $this->session->forget($this->createdAtKey);
        $this->session->forget($this->lastActivityKey);
        $this->session->forget($this->absoluteExpiryKey);
        $this->session->forget($this->tenantSessionKey);
        $this->mfaService->clearPendingChallenge();

        if ($sessionId !== '') {
            $this->sessionManager->deleteById($sessionId);
        }

        if (is_string($rememberValue)) {
            $this->rememberMeService->deleteToken($rememberValue);
        }

        $this->rememberMeService->forgetCookie();

        $this->session->invalidate();
        $this->session->regenerate(true);

        $this->dispatchEvent(new UserLoggedOut(
            $userId,
            $sessionId,
            $this->getClientIp(),
            $this->getDriverMetadata()
        ));
    }

    /**
     * Records a pending MFA challenge without completing login.
     */
    public function startMfaChallenge(
        User $user,
        string $driver = 'otp',
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $ipAddress ??= $this->getClientIp();

        $this->mfaService->startChallenge(
            user: $user,
            driver: $driver,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            sessionId: $this->getSessionId(),
            driverMetadata: $this->getDriverMetadata()
        );
    }

    /**
     * Determines whether the current authenticated user may perform one or more actions.
     *
     * @param  string|array<string> $actions  Single action or list of actions to check.
     * @return bool                           True if allowed.
     */
    public function canPerformAction(string|array $actions): bool
    {
        if (!$this->check()) {
            return false;
        }

        $user = $this->user();

        if (!$user) {
            return false;
        }

        // Allow super-admin shortcut
        if ($user->hasRole('sw-admin')) {
            return true;
        }

        $required = (array) $actions;
        $available = $user->availableActions();

        foreach ($required as $r) {
            if (in_array($r, $available, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if a user is currently authenticated via session.
     */
    /**
     * Determines if a user is currently authenticated via session.
     */
    public function check(): bool
    {
        $this->cachedUser = null;

        $id = $this->id();

        if (!$id && !$this->attemptRememberLogin()) {
            return false;
        }

        $userId = $this->id();
        $sessionId = (string) $this->session->get($this->sessionUidKey);

        if ($sessionId === '' || !$this->sessionManager->isValid($sessionId)) {
            $this->logout();

            return false;
        }

        if (!$userId) {
            $this->logout();

            return false;
        }

        $user = $this->userRepository->findById($userId);

        if (!$user) {
            $this->logout();

            return false;
        }

        if ($this->isExpired()) {
            $this->logout();

            return false;
        }

        $this->cachedUser = $user;

        // Touch activity
        $now = CarbonImmutable::now();
        $this->session->put($this->lastActivityKey, $now->toIso8601String());
        $this->sessionManager->touch($sessionId);

        return true;
    }

    public function id(): ?int
    {
        $val = $this->session->get($this->sessionKey);

        if ($val === null) {
            return null;
        }

        if (is_int($val)) {
            return $val;
        }

        if (is_string($val) && ctype_digit($val)) {
            return (int) $val;
        }

        return null;
    }

    public function user(): ?User
    {
        if (!$this->check()) {
            return null;
        }

        return $this->cachedUser;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function userOrFail(): User
    {
        $id = $this->id();

        if (!$id || !($user = $this->userRepository->findById($id))) {
            throw new ModelNotFoundException('User not found');
        }

        return $user;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserSession>
     */
    public function sessionsForUser(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return $this->sessionManager->sessionsForUser($userId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, UserSession>
     */
    public function allSessions(): \Illuminate\Database\Eloquent\Collection
    {
        return UserSession::query()
            ->orderByDesc('last_activity')
            ->get();
    }

    public function revokeSession(int $userId, string $sessionId): void
    {
        $this->sessionManager->revoke($userId, $sessionId);

        if ($this->session->getId() === $sessionId) {
            $this->logout();
        }
    }

    /**
     * @return array{deleted_sessions:int, cleared_remember_tokens:int}
     */
    public function revokeAllSessionsForUser(
        int $userId,
        bool $includeRememberTokens = false,
    ): array {
        $deleted = $this->sessionManager->revokeAllForUser($userId);

        $activeSessionUserId = (int) $this->session->get($this->sessionKey);

        if ($activeSessionUserId === $userId) {
            $this->logout();
        }

        $clearedRememberTokens = $includeRememberTokens
            ? $this->rememberMeService->revokeForUser($userId)
            : 0;

        return [
            'deleted_sessions' => $deleted,
            'cleared_remember_tokens' => $clearedRememberTokens,
        ];
    }

    public function revokeRememberTokensForUser(int $userId): int
    {
        return $this->rememberMeService->revokeForUser($userId);
    }

    private function getAbsoluteExpiry(CarbonImmutable $now): ?CarbonImmutable
    {
        $absoluteTtl = $this->getConfig(
            'swift-auth.session_lifetimes.absolute_timeout_seconds',
            null
        );

        return $absoluteTtl ? $now->addSeconds($absoluteTtl) : null;
    }

    /**
     * Determines whether the current authenticated user has one or more roles.
     *
     * @param  string|array<string> $roles  Role name or list of role names to check.
     * @return bool                         True if user has at least one of the roles.
     */
    public function hasRole(string|array $roles): bool
    {
        if (!$this->check()) {
            return false;
        }

        $user = $this->user();

        return $user && $user->hasRole($roles);
    }

    private function isExpired(): bool
    {
        $now = CarbonImmutable::now();
        $lastActivity = $this->parseTimestamp((string) $this->session->get($this->lastActivityKey));

        $idleTimeout = $this->getConfig(
            'swift-auth.session_lifetimes.idle_timeout_seconds',
            null
        );

        if ($idleTimeout && $lastActivity && $now->diffInSeconds($lastActivity) > $idleTimeout) {
            return true;
        }

        $absoluteExpiry = $this->parseTimestamp((string) $this->session->get($this->absoluteExpiryKey));

        return $absoluteExpiry !== null && $now->greaterThan($absoluteExpiry);
    }

    private function attemptRememberLogin(): bool
    {
        $user = $this->rememberMeService->attemptLogin();

        if (!$user) {
            return false;
        }

        $shouldRotate = (bool) $this->getConfig('swift-auth.remember_me.rotate_on_use', true);

        $request = function_exists('request') ? request() : null;
        $ip = $request && method_exists($request, 'ip') ? $request->ip() : null;
        $userAgent = $request && method_exists($request, 'userAgent') ? $request->userAgent() : null;

        $this->login(
            user: $user,
            ipAddress: $ip,
            userAgent: $userAgent,
            deviceName: $this->resolveDeviceNameFromRequest(),
            remember: $shouldRotate,
        );

        return true;
    }

    private function resolveDeviceNameFromRequest(): ?string
    {
        try {
            $request = request();

            return method_exists($request, 'header')
                ? (string) $request->header('X-Device-Name', '')
                : null;
        } catch (\Throwable $e) {
            logger()->debug('swift-auth.session.device-name-resolution-failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * @return array{platform:null|string,browser:null|string}
     */
    private function extractAgentMetadata(?string $userAgent): array
    {
        if (!$userAgent) {
            return [
                'platform' => null,
                'browser' => null,
            ];
        }

        $platform = null;
        $browser = null;

        $knownPlatforms = [
            'Windows' => '/windows/i',
            'macOS' => '/macintosh|mac os x/i',
            'iOS' => '/iphone|ipad|ipod/i',
            'Android' => '/android/i',
            'Linux' => '/linux/i',
        ];

        foreach ($knownPlatforms as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $platform = $name;
                break;
            }
        }

        $knownBrowsers = [
            'Chrome' => '/chrome|crios/i',
            'Firefox' => '/firefox|fennec/i',
            'Safari' => '/safari/i',
            'Edge' => '/edg/i',
            'Opera' => '/opera|opr\//i',
        ];

        foreach ($knownBrowsers as $name => $pattern) {
            if (preg_match($pattern, $userAgent)) {
                $browser = $name;
                break;
            }
        }

        return [
            'platform' => $platform,
            'browser' => $browser,
        ];
    }

    /**
     * @return array<int, string>
     */
    public function enforceSessionLimit(
        User $user,
        string $currentSessionId,
    ): array {
        return $this->sessionManager->enforceLimits(
            user: $user,
            currentSessionId: $currentSessionId,
        );
    }

    private function parseTimestamp(string $timestamp): ?CarbonImmutable
    {
        if ($timestamp === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($timestamp);
        } catch (\Throwable $e) {
            logger()->debug('swift-auth.session.timestamp-parse-failed', [
                'timestamp' => $timestamp,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getConfig(string $key, mixed $default): mixed
    {
        try {
            return config($key, $default);
        } catch (\Throwable $e) {
            logger()->error('swift-auth.session.config-access-failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * Dispatches events via the configured dispatcher.
     */
    private function dispatchEvent(object $event): void
    {
        $this->events->dispatch($event);
    }

    /**
     * @return array<string, string>
     */
    private function getDriverMetadata(): array
    {
        $handler = $this->session->getHandler();

        return [
            'handler' => $handler instanceof SessionHandlerInterface ? SessionHandlerInterface::class : (string) $handler,
            'name' => $this->session->getName(),
            'store' => $this->session::class,
        ];
    }

    private function getSessionId(): string
    {
        return (string) $this->session->getId();
    }

    private function getClientIp(): ?string
    {
        if (function_exists('request') && request()) {
            return request()->ip();
        }

        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
