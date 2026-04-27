<?php

/**
 * Tests for SessionManager.
 *
 * PHP 8.2+
 *
 * @package Equidna\SwiftAuth\Tests\Unit\Services
 */

namespace Equidna\SwiftAuth\Tests\Unit\Services;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Classes\Auth\Services\SessionManager;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Models\UserSession;
use Equidna\SwiftAuth\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class SessionManagerTest extends TestCase
{
    private function recordSession(SessionManager $manager, User $user, string $sessionId): void
    {
        $manager->record(
            user: $user,
            sessionId: $sessionId,
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            deviceName: 'UnitTest',
            platform: 'Linux',
            browser: 'CLI',
            lastActivity: CarbonImmutable::now(),
        );
    }

    /**
     * Test that session is cached and validated.
     *
     * @test
     */
    public function test_session_cached_and_validated(): void
    {
        Cache::flush();
        $manager = new SessionManager();
        $user = $this->createTestUser();
        $sessionId = 'session-valid-1';

        $this->recordSession($manager, $user, $sessionId);

        $this->assertTrue($manager->isValid($sessionId));
    }

    /**
     * Test that cached session is validated against DB.
     *
     * @test
     */
    public function test_cache_hit_validates_against_db(): void
    {
        Cache::flush();
        $manager = new SessionManager();
        $user = $this->createTestUser();
        $sessionId = 'session-cache-check';

        $this->recordSession($manager, $user, $sessionId);

        $this->assertTrue($manager->isValid($sessionId));

        UserSession::where('session_id', $sessionId)->delete();

        $this->assertFalse($manager->isValid($sessionId));
    }

    /**
     * Test that isValid() performs DB check after cache hit.
     *
     * @test
     */
    public function test_is_valid_performs_explicit_db_check(): void
    {
        Cache::flush();
        $manager = new SessionManager();
        $user = $this->createTestUser();
        $sessionId = 'session-explicit-db';

        $this->recordSession($manager, $user, $sessionId);
        $this->assertTrue($manager->isValid($sessionId));

        UserSession::where('session_id', $sessionId)->delete();

        $isValid = $manager->isValid($sessionId);

        $this->assertFalse($isValid);
    }

    /**
     * Test that session touch updates last_activity.
     *
     * @test
     */
    public function test_session_touch_updates_activity(): void
    {
        Cache::flush();
        $manager = new SessionManager();
        $user = $this->createTestUser();
        $sessionId = 'session-touch';

        $this->recordSession($manager, $user, $sessionId);

        $originalSession = UserSession::where('session_id', $sessionId)->firstOrFail();
        $originalActivity = $originalSession->last_activity;

        $manager->touch($sessionId);

        $updatedSession = UserSession::where('session_id', $sessionId)->firstOrFail();
        $this->assertGreaterThanOrEqual($originalActivity->timestamp, $updatedSession->last_activity->timestamp);
    }

    /**
     * Test that concurrent session limit is enforced.
     *
     * @test
     */
    public function test_concurrent_session_limit_enforced(): void
    {
        Cache::flush();
        $manager = new SessionManager();
        $user = $this->createTestUser();
        config(['swift-auth.session_limits.max_sessions' => 3]);
        config(['swift-auth.session_limits.eviction' => 'oldest']);

        for ($i = 1; $i <= 4; $i++) {
            $this->recordSession($manager, $user, 'session-limit-' . $i);
        }

        $evictedIds = $manager->enforceLimits($user, 'session-limit-4');
        $activeCount = UserSession::where('id_user', $user->getKey())->count();

        $this->assertNotEmpty($evictedIds);
        $this->assertLessThanOrEqual(3, $activeCount);
    }
}
