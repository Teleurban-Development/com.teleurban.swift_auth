<?php

/**
 * Tests for MfaService.
 *
 * PHP 8.2+
 *
 * @package Equidna\SwiftAuth\Tests\Unit\Services
 */

namespace Equidna\SwiftAuth\Tests\Unit\Services;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Tests\TestCase;
use Illuminate\Support\Facades\Cache;

class MfaServiceTest extends TestCase
{
    /**
     * Test that MFA challenge is set with expiration.
     *
     * @test
     */
    public function test_mfa_challenge_expiration_set(): void
    {
        // Arrange
        Cache::flush();
        $service = new MfaService();
        $user = User::factory()->create();

        // Act
        $service->startChallenge($user->getKey(), 'otp');

        // Assert
        $pendingKey = 'swift-auth.pending-mfa.' . $user->getKey();
        $expiresKey = 'swift-auth.pending-mfa-expires.' . $user->getKey();

        $this->assertTrue(Cache::has($pendingKey));
        $this->assertTrue(Cache::has($expiresKey));
    }

    /**
     * Test that valid pending challenge passes validation.
     *
     * @test
     */
    public function test_valid_pending_challenge_passes(): void
    {
        // Arrange
        Cache::flush();
        $service = new MfaService();
        $user = User::factory()->create();

        // Act
        $service->startChallenge($user->getKey(), 'otp');
        $isValid = $service->isPendingChallengeValid($user->getKey());

        // Assert
        $this->assertTrue($isValid);
    }

    /**
     * Test that expired pending challenge fails validation.
     *
     * @test
     */
    public function test_expired_pending_challenge_fails(): void
    {
        // Arrange
        Cache::flush();
        $service = new MfaService();
        $user = User::factory()->create();

        // Act
        $service->startChallenge($user->getKey(), 'otp');

        // Manually set expiration to the past
        $expiresKey = 'swift-auth.pending-mfa-expires.' . $user->getKey();
        Cache::put($expiresKey, CarbonImmutable::now()->subMinutes(1)->timestamp, now()->addHours(1));

        $isValid = $service->isPendingChallengeValid($user->getKey());

        // Assert
        $this->assertFalse($isValid);
    }

    /**
     * Test that missing pending challenge fails validation.
     *
     * @test
     */
    public function test_missing_pending_challenge_fails(): void
    {
        // Arrange
        Cache::flush();
        $service = new MfaService();
        $nonExistentUserId = 99999;

        // Act
        $isValid = $service->isPendingChallengeValid($nonExistentUserId);

        // Assert
        $this->assertFalse($isValid);
    }

    /**
     * Test that challenge is cleared on clearPendingChallenge.
     *
     * @test
     */
    public function test_clear_pending_challenge(): void
    {
        // Arrange
        Cache::flush();
        $service = new MfaService();
        $user = User::factory()->create();

        // Act
        $service->startChallenge($user->getKey(), 'otp');
        $service->clearPendingChallenge($user->getKey());

        // Assert
        $pendingKey = 'swift-auth.pending-mfa.' . $user->getKey();
        $expiresKey = 'swift-auth.pending-mfa-expires.' . $user->getKey();

        $this->assertFalse(Cache::has($pendingKey));
        $this->assertFalse(Cache::has($expiresKey));
    }
}
