<?php

/**
 * Event fired when a multi-factor authentication challenge is started.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Classes\Auth\Events
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Classes\Auth\Events;

/**
 * Dispatched when MFA challenge begins.
 */
final class MfaChallengeStarted
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
