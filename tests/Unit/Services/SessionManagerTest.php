<?php

/**
 * Tests for SessionManager.
 *
 * PHP 8.2+
 *
 * @package Equidna\SwiftAuth\Tests\Unit\Services
 */

namespace Equidna\SwiftAuth\Tests\Unit\Services;

use Equidna\SwiftAuth\Classes\Auth\Services\SessionManager;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Models\UserSession;
use Equidna\SwiftAuth\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class SessionManagerTest extends TestCase
{
    /**
     * Test that session is cached and validated.
     *
     * @test
     */
    public function test_session_cached_and_validated(): void
    {
        // Arrange
        Cache::flush();
        $manager = new SessionManager();
        $user = User::factory()->create();

        // Act
        $sessionId = $manager->record($user->getKey(), request()->ip(), request()->userAgent());
        $session = $manager->find($sessionId);

        // Assert
        $this->assertNotNull($session);
        $this->assertEquals($user->getKey(), $session->id_user);
    }

    /**
     * Test that cached session is validated against DB.
     *
     * @test
     */
    public function test_cache_hit_validates_against_db(): void
    {
        // Arrange
        Cache::flush();
        $manager = new SessionManager();
        $user = User::factory()->create();
        $sessionId = $manager->record($user->getKey(), request()->ip(), request()->userAgent());

        // Act - First access populates cache
        $session1 = $manager->find($sessionId);

        // Assert first is valid
        $this->assertNotNull($session1);

        // Act - Delete from DB to ensure cache is checked against DB
        UserSession::where('id_session', $sessionId)->delete();

        // Act - Second access should detect deletion
        $session2 = $manager->find($sessionId);

        // Assert DB validation caught the deletion
        $this->assertNull($session2);
    }

    /**
     * Test that isValid() performs DB check after cache hit.
     *
     * @test
     */
    public function test_is_valid_performs_explicit_db_check(): void
    {
        // Arrange
        Cache::flush();
        $manager = new SessionManager();
        $user = User::factory()->create();
        $sessionId = $manager->record($user->getKey(), request()->ip(), request()->userAgent());

        // Act - Populate cache
        $manager->find($sessionId);

        // Act - Invalidate in DB
        UserSession::where('id_session', $sessionId)->update(['is_active' => false]);

        // Act - Call isValid
        $isValid = $manager->isValid($sessionId);

        // Assert
        $this->assertFalse($isValid);
    }

    /**
     * Test that session touch updates last_activity.
     *
     * @test
     */
    public function test_session_touch_updates_activity(): void
    {
        // Arrange
        Cache::flush();
        $manager = new SessionManager();
        $user = User::factory()->create();
        $sessionId = $manager->record($user->getKey(), request()->ip(), request()->userAgent());

        // Get original last_activity
        $originalSession = UserSession::find($sessionId);
        $originalActivity = $originalSession->last_activity;

        sleep(1);

        // Act
        $manager->touch($sessionId);

        // Assert
        $updatedSession = UserSession::find($sessionId);
        $this->assertGreaterThan($originalActivity->timestamp, $updatedSession->last_activity->timestamp);
    }

    /**
     * Test that concurrent session limit is enforced.
     *
     * @test
     */
    public function test_concurrent_session_limit_enforced(): void
    {
        // Arrange
        Cache::flush();
        $manager = new SessionManager();
        $user = User::factory()->create();
        $maxConcurrentSessions = 3;

        // Act - Create multiple sessions
        $sessionIds = [];
        for ($i = 0; $i < $maxConcurrentSessions + 1; $i++) {
            $sessionId = $manager->record($user->getKey(), '127.0.0.1', 'Test Agent');
            $sessionIds[] = $sessionId;
        }

        // Assert - First sessions exist, last may be evicted based on policy
        $activeSessions = UserSession::where('id_user', $user->getKey())
            ->where('is_active', true)
            ->count();

        // Verify limit is at or below max
        $this->assertLessThanOrEqual($maxConcurrentSessions, $activeSessions);
    }
}
