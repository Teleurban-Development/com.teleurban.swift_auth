<?php

/**
 * Tests for MfaService.
 *
 * PHP 8.2+
 *
 * @package Equidna\SwiftAuth\Tests\Unit\Services
 */

namespace Equidna\SwiftAuth\Tests\Unit\Services;

use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\SwiftAuth\Tests\TestCase;
use Illuminate\Support\Facades\Session;

class MfaServiceTest extends TestCase
{
    /**
     * Test that MFA challenge is set with expiration.
     *
     * @test
     */
    public function test_mfa_challenge_expiration_set(): void
    {
        $service = $this->app->make(MfaService::class);
        $user = $this->createTestUser();

        $service->startChallenge($user, 'otp');

        $this->assertSame($user->getKey(), Session::get('swift_auth_pending_mfa_user_id'));
        $this->assertSame('otp', Session::get('swift_auth_pending_mfa_driver'));
        $this->assertNotNull(Session::get('swift_auth_pending_mfa_expires'));
    }

    /**
     * Test that valid pending challenge passes validation.
     *
     * @test
     */
    public function test_valid_pending_challenge_passes(): void
    {
        $service = $this->app->make(MfaService::class);
        $user = $this->createTestUser();

        $service->startChallenge($user, 'otp');
        $isValid = $service->isPendingChallengeValid();

        $this->assertTrue($isValid);
    }

    /**
     * Test that expired pending challenge fails validation.
     *
     * @test
     */
    public function test_expired_pending_challenge_fails(): void
    {
        $service = $this->app->make(MfaService::class);
        $user = $this->createTestUser();

        $service->startChallenge($user, 'otp');

        Session::put('swift_auth_pending_mfa_expires', now()->subMinute()->toIso8601String());

        $isValid = $service->isPendingChallengeValid();

        $this->assertFalse($isValid);
    }

    /**
     * Test that missing pending challenge fails validation.
     *
     * @test
     */
    public function test_missing_pending_challenge_fails(): void
    {
        $service = $this->app->make(MfaService::class);

        Session::forget('swift_auth_pending_mfa_expires');

        $isValid = $service->isPendingChallengeValid();

        $this->assertFalse($isValid);
    }

    /**
     * Test that challenge is cleared on clearPendingChallenge.
     *
     * @test
     */
    public function test_clear_pending_challenge(): void
    {
        $service = $this->app->make(MfaService::class);
        $user = $this->createTestUser();

        $service->startChallenge($user, 'otp');
        $service->clearPendingChallenge();

        $this->assertNull(Session::get('swift_auth_pending_mfa_user_id'));
        $this->assertNull(Session::get('swift_auth_pending_mfa_driver'));
        $this->assertNull(Session::get('swift_auth_pending_mfa_expires'));
    }
}
