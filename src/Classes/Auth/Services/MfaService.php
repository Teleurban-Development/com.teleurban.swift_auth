<?php

namespace Equidna\SwiftAuth\Classes\Auth\Services;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Classes\Auth\Events\MfaChallengeStarted;
use Equidna\SwiftAuth\Models\User;
use Illuminate\Events\Dispatcher;
use Illuminate\Session\Store as Session;

/**
 * Manages Multi-Factor Authentication challenges and state.
 */
class MfaService
{
    protected string $pendingMfaUserKey = 'swift_auth_pending_mfa_user_id';
    protected string $pendingMfaDriverKey = 'swift_auth_pending_mfa_driver';
    protected string $pendingMfaExpiresKey = 'swift_auth_pending_mfa_expires';

    public function __construct(
        protected Session $session,
        protected Dispatcher $events,
    ) {}

    /**
     * Records a pending MFA challenge without completing login.
     * Sets a 10-minute expiration to prevent session hijacking.
     *
     * @param array<string, mixed> $driverMetadata
     */
    public function startChallenge(
        User $user,
        string $driver = 'otp',
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        array $driverMetadata = []
    ): void {
        $this->session->put($this->pendingMfaUserKey, $user->getKey());
        $this->session->put($this->pendingMfaDriverKey, $driver);

        // Set 10-minute expiration for the pending challenge
        $this->session->put(
            $this->pendingMfaExpiresKey,
            CarbonImmutable::now()->addMinutes(10)->toIso8601String()
        );

        $this->events->dispatch(new MfaChallengeStarted(
            $user->getKey(),
            $sessionId ?? $this->session->getId(),
            $ipAddress,
            $driverMetadata
        ));

        logger()->info('swift-auth.mfa.challenge_started', [
            'user_id' => $user->getKey(),
            'driver' => $driver,
            'ip' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Clears all pending MFA challenge data including expiration.
     */
    public function clearPendingChallenge(): void
    {
        $this->session->forget($this->pendingMfaUserKey);
        $this->session->forget($this->pendingMfaDriverKey);
        $this->session->forget($this->pendingMfaExpiresKey);
    }

    /**
     * Returns the pending MFA user ID from the session, or null if not set.
     *
     * @return int|string|null
     */
    public function getPendingUserId(): int|string|null
    {
        $val = $this->session->get($this->pendingMfaUserKey);
        return is_int($val) || is_string($val) ? $val : null;
    }

    /**
     * Returns the pending MFA driver from the session, or null if not set.
     */
    public function getPendingDriver(): ?string
    {
        $val = $this->session->get($this->pendingMfaDriverKey);
        return is_string($val) ? $val : null;
    }

    /**
     * Checks if a pending MFA challenge is still valid (not expired).
     *
     * @return bool True if challenge exists and hasn't expired, false otherwise.
     */
    public function isPendingChallengeValid(): bool
    {
        $expiresAt = $this->session->get($this->pendingMfaExpiresKey);
        if (!$expiresAt) {
            return false;
        }

        try {
            $expiry = CarbonImmutable::parse($expiresAt);
            return $expiry->isFuture();
        } catch (\Exception) {
            return false;
        }
    }
}
