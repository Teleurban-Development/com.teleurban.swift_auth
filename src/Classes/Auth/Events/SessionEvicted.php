<?php

/**
 * Event fired when a session is evicted to enforce limits.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Classes\Auth\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Classes\Auth\Events;

/**
 * Dispatched when session limits are enforced.
 */
final class SessionEvicted
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
