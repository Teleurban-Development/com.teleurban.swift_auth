<?php

namespace Equidna\SwiftAuth\Tests\Feature\Auth;

use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\SwiftAuth\Classes\Auth\SwiftSessionAuth;
use Equidna\SwiftAuth\Tests\TestCase;

class SwiftSessionAuthFlowTest extends TestCase
{
    public function test_login_sets_session_and_authenticates_user(): void
    {
        $user = $this->createTestUser();
        $auth = $this->app->make(SwiftSessionAuth::class);

        $result = $auth->login(
            user: $user,
            ipAddress: '127.0.0.1',
            userAgent: 'PHPUnit',
            deviceName: 'FeatureTest',
            remember: false,
        );

        $this->assertArrayHasKey('evicted_session_ids', $result);
        $this->assertTrue($auth->check());
        $this->assertSame($user->getKey(), $auth->id());
    }

    public function test_logout_clears_authentication_state(): void
    {
        $user = $this->createTestUser();
        $auth = $this->app->make(SwiftSessionAuth::class);

        $auth->login(user: $user);
        $this->assertTrue($auth->check());

        $auth->logout();

        $this->assertFalse($auth->check());
        $this->assertNull($auth->id());
    }

    public function test_mfa_pending_challenge_expiration_is_enforced(): void
    {
        $user = $this->createTestUser();
        $mfa = $this->app->make(MfaService::class);

        $mfa->startChallenge($user, 'otp');
        $this->assertTrue($mfa->isPendingChallengeValid());

        session()->put('swift_auth_pending_mfa_expires', now()->subMinute()->toIso8601String());

        $this->assertFalse($mfa->isPendingChallengeValid());
    }
}
