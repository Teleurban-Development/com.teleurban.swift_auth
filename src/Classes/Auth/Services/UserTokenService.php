<?php

/**
 * Service for managing user API tokens.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Classes\Auth\Services
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth Package repository
 */

namespace Equidna\SwiftAuth\Classes\Auth\Services;

use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Models\UserToken;

/**
 * Manages API token creation, validation, and revocation.
 *
 * Handles token hashing (SHA-256), ability checks, expiration, and usage tracking.
 * Follows SwiftAuth token patterns for consistency with RememberToken and PasswordResetToken.
 */
class UserTokenService
{
    /**
     * Creates a new API token for a user.
     *
     * @param  User                 $user       User to create token for.
     * @param  string               $name       Token name/label.
     * @param  array<string>        $abilities  Scopes/permissions (empty = wildcard).
     * @param  \DateTimeInterface|null $expiresAt  Expiration timestamp (null = no expiration).
     * @return array{token: string, model: UserToken}  Plain token and model instance.
     */
    public function createToken(
        User $user,
        string $name,
        array $abilities = [],
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $plainToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $plainToken);

    // Normalize empty abilities to wildcard
    $normalizedAbilities = empty($abilities) ? ['*'] : $abilities;

        $tokenModel = UserToken::create([
            'id_user' => $user->id_user,
            'name' => $name,
            'hashed_token' => $hashedToken,
            'abilities' => $normalizedAbilities,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'model' => $tokenModel,
        ];
    }

    /**
     * Validates a plain token and returns the associated token model.
     *
     * @param  string            $plainToken  Plain token to validate.
     * @return UserToken|null                 Token model or null if invalid/expired.
     */
    public function validateToken(string $plainToken): ?UserToken
    {
        $hashedToken = hash('sha256', $plainToken);

        /** @var UserToken|null $token */
        $token = UserToken::query()
            ->where('hashed_token', $hashedToken)
            ->first();

        if ($token === null) {
            return null;
        }

        if ($token->isExpired()) {
            return null;
        }

        return $token;
    }

    /**
     * Validates a token and checks if it has a specific ability.
     *
     * @param  string $plainToken  Plain token to validate.
     * @param  string $ability     Required ability.
     * @return bool                True if valid and has ability.
     */
    public function canPerformAction(string $plainToken, string $ability): bool
    {
        $token = $this->validateToken($plainToken);

        if ($token === null) {
            return false;
        }

        return $token->can($ability);
    }

    /**
     * Revokes a specific token by ID.
     *
     * @param  int  $tokenId  Token ID to revoke.
     * @return bool           True if revoked successfully.
     */
    public function revokeToken(int $tokenId): bool
    {
        return UserToken::query()
            ->where('id_user_token', $tokenId)
            ->delete() > 0;
    }

    /**
     * Revokes all tokens for a user.
     *
     * @param  int $userId  User ID.
     * @return int          Number of tokens revoked.
     */
    public function revokeAllUserTokens(int $userId): int
    {
        return UserToken::query()
            ->where('id_user', $userId)
            ->delete();
    }

    /**
     * Purges all expired tokens from the database.
     *
     * @return int  Number of tokens purged.
     */
    public function purgeExpiredTokens(): int
    {
        return UserToken::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }
}
