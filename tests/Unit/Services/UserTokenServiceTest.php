<?php

/**
 * Tests for UserTokenService.
 *
 * PHP 8.2+
 *
 * @package Equidna\SwiftAuth\Tests\Unit\Services
 */

namespace Equidna\SwiftAuth\Tests\Unit\Services;

use Equidna\SwiftAuth\Classes\Auth\Services\UserTokenService;
use Equidna\SwiftAuth\Tests\TestCase;

class UserTokenServiceTest extends TestCase
{
    /**
     * Test that empty abilities array is normalized to wildcard ['*'].
     *
     * @test
     */
    public function test_empty_abilities_normalized_to_wildcard(): void
    {
        // Arrange
        $service = new UserTokenService();
        $user = $this->createTestUser();

        // Act
        $result = $service->createToken(
            user: $user,
            name: 'Test Token',
            abilities: [], // Empty array
        );

        // Assert
        $this->assertEquals(['*'], $result['model']->abilities);
        $this->assertTrue($result['model']->can('any-action'));
    }

    /**
     * Test that null abilities defaults to wildcard.
     *
     * @test
     */
    public function test_null_abilities_defaults_to_wildcard(): void
    {
        // Arrange
        $service = new UserTokenService();
        $user = $this->createTestUser();

        // Act
        $result = $service->createToken(
            user: $user,
            name: 'Test Token',
        );

        // Assert
        $this->assertEquals(['*'], $result['model']->abilities);
    }

    /**
     * Test that specific abilities are preserved.
     *
     * @test
     */
    public function test_specific_abilities_preserved(): void
    {
        // Arrange
        $service = new UserTokenService();
        $user = $this->createTestUser();
        $abilities = ['posts:read', 'posts:write'];

        // Act
        $result = $service->createToken(
            user: $user,
            name: 'Test Token',
            abilities: $abilities,
        );

        // Assert
        $this->assertEquals($abilities, $result['model']->abilities);
        $this->assertTrue($result['model']->can('posts:read'));
        $this->assertTrue($result['model']->can('posts:write'));
        $this->assertFalse($result['model']->can('users:delete'));
    }

    /**
     * Test that token creation includes plain token in result.
     *
     * @test
     */
    public function test_token_creation_returns_plain_token(): void
    {
        // Arrange
        $service = new UserTokenService();
        $user = $this->createTestUser();

        // Act
        $result = $service->createToken(
            user: $user,
            name: 'Test Token',
        );

        // Assert
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertNotEmpty($result['token']);
        $this->assertTrue(strlen($result['token']) > 0);
    }

    /**
     * Test that token can be validated.
     *
     * @test
     */
    public function test_token_validation(): void
    {
        // Arrange
        $service = new UserTokenService();
        $user = $this->createTestUser();
        $created = $service->createToken($user, 'Test Token');
        $plainToken = $created['token'];

        // Act
        $validated = $service->validateToken($plainToken);

        // Assert
        $this->assertNotNull($validated);
        $this->assertEquals($user->id_user, $validated->id_user);
    }

    /**
     * Test that invalid token returns null.
     *
     * @test
     */
    public function test_invalid_token_returns_null(): void
    {
        // Arrange
        $service = new UserTokenService();

        // Act
        $result = $service->validateToken('invalid-token-that-does-not-exist');

        // Assert
        $this->assertNull($result);
    }
}
