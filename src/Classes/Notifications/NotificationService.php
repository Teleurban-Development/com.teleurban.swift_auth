<?php

/**
 * Service for sending email notifications via Bird Flock.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Services
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Classes\Notifications;

use Equidna\BirdFlock\BirdFlock;
use Equidna\BirdFlock\DTO\FlightPlan;
use Equidna\SwiftAuth\Classes\Notifications\DTO\NotificationResult;
use RuntimeException;

/**
 * Handles email notifications using Bird Flock messaging bus.
 */
class NotificationService
{
    /**
     * Sends password reset email.
     *
     * @param  string              $email  Recipient email address.
     * @param  string              $token  Password reset token.
     * @return NotificationResult          Dispatch result with success status and message ID or error.
     */
    public function sendPasswordReset(
        string $email,
        string $token,
    ): NotificationResult {
        try {
            $routeNamePrefix = config('swift-auth.route_prefix', 'swift-auth');

            $resetUrl = route(
                $routeNamePrefix . '.password.reset.form',
                parameters: [
                    'token' => $token,
                    'email' => $email,
                ],
                absolute: true,
            );

            /** @var string $subject */
            $subject = __('swift-auth::email.reset_subject');
            $flight = new FlightPlan(
                channel: 'email',
                to: $email,
                subject: $subject,
                html: $this->getPasswordResetHtml($resetUrl, $email),
                text: $this->getPasswordResetText($resetUrl),
                idempotencyKey: "swift-auth:password-reset:{$email}:{$token}",
            );

            $messageId = BirdFlock::dispatch($flight);

            return NotificationResult::success($messageId);
        } catch (RuntimeException $e) {
            logger()->error(
                'swift-auth.notification.password-reset-failed',
                [
                    'email_hash' => hash('sha256', $email),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ],
            );

            return NotificationResult::failure($e->getMessage());
        }
    }

    /**
     * Sends email verification email.
     *
     * @param  string              $email  Recipient email address.
     * @param  string              $token  Email verification token.
     * @return NotificationResult          Dispatch result with success status and message ID or error.
     */
    public function sendEmailVerification(
        string $email,
        string $token,
    ): NotificationResult {
        try {
            $routeNamePrefix = config('swift-auth.route_prefix', 'swift-auth');

            $verifyUrl = route(
                $routeNamePrefix . '.email.verify',
                parameters: [
                    'token' => $token,
                    'email' => $email,
                ],
                absolute: true,
            );

            /** @var string $subject */
            $subject = __('swift-auth::email.verification_subject');
            $flight = new FlightPlan(
                channel: 'email',
                to: $email,
                subject: $subject,
                html: $this->getEmailVerificationHtml($verifyUrl, $email),
                text: $this->getEmailVerificationText($verifyUrl),
                idempotencyKey: "swift-auth:email-verification:{$email}:{$token}",
            );

            $messageId = BirdFlock::dispatch($flight);

            return NotificationResult::success($messageId);
        } catch (RuntimeException $e) {
            logger()->error(
                'swift-auth.notification.email-verification-failed',
                [
                    'email_hash' => hash('sha256', $email),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ],
            );

            return NotificationResult::failure($e->getMessage());
        }
    }

    /**
     * Sends account lockout notification.
     *
     * @param  string              $email     Recipient email address.
     * @param  int                 $duration  Lockout duration in seconds.
     * @return NotificationResult             Dispatch result with success status and message ID or error.
     */
    public function sendAccountLockout(
        string $email,
        int $duration,
    ): NotificationResult {
        try {
            $minutes = (int) ceil($duration / 60);

            /** @var string $subject */
            $subject = __('swift-auth::email.lockout_subject');
            $flight = new FlightPlan(
                channel: 'email',
                to: $email,
                subject: $subject,
                html: $this->getAccountLockoutHtml($email, $minutes),
                text: $this->getAccountLockoutText($minutes),
                idempotencyKey: "swift-auth:account-lockout:{$email}:" . now()->getTimestamp(),
            );

            $messageId = BirdFlock::dispatch($flight);

            return NotificationResult::success($messageId);
        } catch (RuntimeException $e) {
            logger()->error(
                'swift-auth.notification.account-lockout-failed',
                [
                    'email_hash' => hash('sha256', $email),
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ],
            );

            return NotificationResult::failure($e->getMessage());
        }
    }

    private function getPasswordResetHtml(
        string $resetUrl,
        string $email,
    ): string {
        return view(
            'swift-auth::emails.password_reset',
            [
                'resetUrl' => $resetUrl,
                'email' => $email,
            ],
        )->render();
    }

    private function getPasswordResetText(string $resetUrl): string
    {
        return view(
            'swift-auth::emails.password_reset_text',
            [
                'resetUrl' => $resetUrl,
            ],
        )->render();
    }

    private function getEmailVerificationHtml(
        string $verifyUrl,
        string $email,
    ): string {
        return view(
            'swift-auth::emails.verification',
            [
                'verifyUrl' => $verifyUrl,
                'email' => $email,
            ],
        )->render();
    }

    private function getEmailVerificationText(string $verifyUrl): string
    {
        return view(
            'swift-auth::emails.verification_text',
            [
                'verifyUrl' => $verifyUrl,
            ],
        )->render();
    }

    private function getAccountLockoutHtml(
        string $email,
        int $minutes,
    ): string {
        return view(
            'swift-auth::emails.account_lockout',
            [
                'email' => $email,
                'minutes' => $minutes,
            ],
        )->render();
    }

    private function getAccountLockoutText(int $minutes): string
    {
        return view(
            'swift-auth::emails.account_lockout_text',
            [
                'minutes' => $minutes,
            ],
        )->render();
    }
}
