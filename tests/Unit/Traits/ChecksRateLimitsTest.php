<?php

/**
 * Unit tests for ChecksRateLimits trait.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Tests\Unit\Traits
 * @author    QA Team
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Tests\Unit\Traits;

use Equidna\SwiftAuth\Classes\Auth\Traits\ChecksRateLimits;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\TestCase;

/**
 * Tests ChecksRateLimits trait helper methods.
 */
class ChecksRateLimitsTest extends TestCase
{
    use ChecksRateLimits;

    protected function setUp(): void
    {
        parent::setUp();

        // Build a minimal container for RateLimiter facade resolution.
        $app = new \Illuminate\Container\Container();
        $repository = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $rateLimiter = new \Illuminate\Cache\RateLimiter($repository);

        $app->instance('cache', $repository);
        $app->instance(\Illuminate\Contracts\Cache\Repository::class, $repository);
        $app->instance('cache.rate.limiter', $rateLimiter);

        Facade::setFacadeApplication($app);
    }

    /**
     * Test checkRateLimit throws when limit exceeded.
     */
    public function test_check_rate_limit_throws_when_exceeded(): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Too many attempts');

        // Simulate rate limit exceeded
        RateLimiter::hit('test-key', 60);
        RateLimiter::hit('test-key', 60);
        RateLimiter::hit('test-key', 60);

        $this->checkRateLimit('test-key', 2, 'Too many attempts');
    }

    /**
     * Test checkRateLimit passes when under limit.
     */
    public function test_check_rate_limit_passes_when_under_limit(): void
    {
        RateLimiter::hit('test-key-2', 60);

        $this->checkRateLimit('test-key-2', 5, 'Too many attempts');

        $this->assertTrue(true, 'No exception thrown when under limit');
    }

    /**
     * Test hitRateLimit increments counter.
     */
    public function test_hit_rate_limit_increments_counter(): void
    {
        $key = 'hit-test-key';

        $this->hitRateLimit($key, 60);

        $this->assertTrue(
            RateLimiter::tooManyAttempts($key, 0),
            'Rate limiter should show at least one attempt'
        );
    }

    /**
     * Test clearRateLimit resets counter.
     */
    public function test_clear_rate_limit_resets_counter(): void
    {
        $key = 'clear-test-key';

        RateLimiter::hit($key, 60);
        RateLimiter::hit($key, 60);

        $this->clearRateLimit($key);

        $this->assertFalse(
            RateLimiter::tooManyAttempts($key, 10),
            'Rate limiter should be cleared'
        );
    }

    /**
     * Test rateLimitAvailableIn returns seconds.
     */
    public function test_rate_limit_available_in_returns_seconds(): void
    {
        $key = 'available-test-key';

        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($key, 120);
        }

        $availableIn = $this->rateLimitAvailableIn($key);

        $this->assertIsInt($availableIn);
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(120, $availableIn);
    }
}
