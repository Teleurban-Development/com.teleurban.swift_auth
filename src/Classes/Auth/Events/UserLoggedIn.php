<?php

/**
 * Event fired when a user successfully logs in.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Classes\Auth\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Classes\Auth\Events;

/**
 * Dispatched after successful user authentication.
 */
final class UserLoggedIn
{
    /**
     * @param array<string, mixed> $driverMetadata
     */
    public function __construct(
        public readonly int|string|null $userId,
        public readonly string $sessionId,
        public readonly ?string $ipAddress,
        public readonly array $driverMetadata
    ) {}
}
