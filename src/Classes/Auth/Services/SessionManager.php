<?php

namespace Equidna\SwiftAuth\Classes\Auth\Services;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Models\UserSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;

/**
 * Manages user session records and concurrency limits.
 */
class SessionManager
{
    /**
     * Records a user session in the database.
     */
    public function record(
        User $user,
        string $sessionId,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        ?string $platform,
        ?string $browser,
        CarbonImmutable $lastActivity,
    ): void {
        try {
            UserSession::query()->updateOrCreate(
                [
                    'session_id' => $sessionId,
                ],
                [
                    'id_user' => $user->getKey(),
                    'ip_address' => $ipAddress,
                    'user_agent' => $userAgent,
                    'device_name' => $deviceName,
                    'platform' => $platform,
                    'browser' => $browser,
                    'last_activity' => $lastActivity,
                ],
            );
        } catch (QueryException $exception) {
            logger()->warning('swift-auth.session.record_failed', [
                'session_id' => $sessionId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Enforces concurrent session limits.
     *
     * @return array<int, string> List of evicted session IDs.
     */
    public function enforceLimits(
        User $user,
        string $currentSessionId,
    ): array {
        $maxSessions = config('swift-auth.session_limits.max_sessions');

        if ($maxSessions === null) {
            return [];
        }

        $maxSessions = (int) $maxSessions;

        if ($maxSessions <= 0) {
            return [];
        }

        $policy = (string) config('swift-auth.session_limits.eviction', 'oldest');

        try {
            $sessions = UserSession::query()
                ->where('id_user', $user->getKey())
                ->orderByDesc('last_activity')
                ->get();

            if ($sessions->count() <= $maxSessions) {
                return [];
            }

            // Exclude current session from eviction candidates if possible
            $candidates = $sessions->reject(fn ($s) => $s->session_id === $currentSessionId);

            // If we still have too many, we must evict something
            $excessCount = $sessions->count() - $maxSessions;

            // Re-calculate excess considering we want to keep current session
            // Logic: we want 5 total. we have 6. need to evict 1.
            // if current is in the list, we keep it. so we evict from the others.

            if ($policy === 'newest') {
                $evicted = $candidates->take($excessCount);
            } else {
                // oldest
                $evicted = $candidates->reverse()->take($excessCount);
            }

            $evictedIds = $evicted->pluck('session_id')->all();

            if (!empty($evictedIds)) {
                UserSession::query()->whereIn('session_id', $evictedIds)->delete();
            }

            return $evictedIds;
        } catch (QueryException $exception) {
            logger()->error('swift-auth.session.limit_enforcement_failed', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    public function isValid(string $sessionId): bool
    {
        $ttl = (int) config('swift-auth.performance.session_cache_ttl', 10);

        if ($ttl <= 0) {
             return $this->checkDb($sessionId);
        }

        $key = "swift_auth:session_valid:{$sessionId}";

        // We use the driver configured in cache.default, or fallback to file/array if needed.
        // Assuming app has cache configured.
        $cached = Cache::get($key);
        if ($cached !== null) {
            // Cache hit, but verify expiration hasn't occurred since cache was set
            if ($cached instanceof \DateTime || is_string($cached)) {
                // For backward compatibility, just check DB to ensure session still exists
                return $this->checkDb($sessionId);
            }
            return (bool) $cached;
        }

        // Cache miss, check DB and cache result
        $valid = $this->checkDb($sessionId);
        Cache::put($key, $valid, $ttl);
        return $valid;
    }

    protected function checkDb(string $sessionId): bool
    {
        try {
            return UserSession::query()
               ->where('session_id', $sessionId)
               ->exists();
        } catch (QueryException $exception) {
            logger()->warning('swift-auth.session.validation_failed', [
               'session_id' => $sessionId,
               'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function touch(string $sessionId): void
    {
        try {
             UserSession::query()
                ->where('session_id', $sessionId)
                ->update([
                    'last_activity' => CarbonImmutable::now(),
                ]);
          } catch (QueryException $exception) {
             logger()->warning('swift-auth.session.touch_failed', [
                'session_id' => $sessionId,
                'error' => $exception->getMessage(),
             ]);
        }
    }

    public function revoke(int $userId, string $sessionId): void
    {
        Cache::forget("swift_auth:session_valid:{$sessionId}");

        UserSession::query()
            ->where('id_user', $userId)
            ->where('session_id', $sessionId)
            ->delete();
    }

    public function revokeAllForUser(int $userId): int
    {
        // We need to find the session IDs to clear current cache keys
        $sessions = UserSession::query()
            ->where('id_user', $userId)
            ->get(['session_id']);

        foreach ($sessions as $session) {
             Cache::forget("swift_auth:session_valid:{$session->session_id}");
        }

        return UserSession::query()
            ->where('id_user', $userId)
            ->delete();
    }

    public function deleteById(string $sessionId): void
    {
         Cache::forget("swift_auth:session_valid:{$sessionId}");

         UserSession::query()
            ->where('session_id', $sessionId)
            ->delete();
    }

    /**
     * @return Collection<int, UserSession>
     */
    public function sessionsForUser(int $userId): Collection
    {
        return UserSession::query()
            ->where('id_user', $userId)
            ->orderByDesc('last_activity')
            ->get();
    }
}
