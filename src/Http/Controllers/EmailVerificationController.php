<?php

/**
 * Handles email verification flow.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Classes\Notifications\NotificationService;

/**
 * Manages email verification process.
 */
final class EmailVerificationController
{
    /**
     * Sends email verification link.
     *
     * Enforces rate limiting, generates secure token, and dispatches verification email.
     *
     * @param  Request              $request              HTTP request.
     * @param  NotificationService  $notificationService  Email service.
     * @return JsonResponse                               Success or error response.
     */
    public function send(
        Request $request,
        NotificationService $notificationService,
    ): JsonResponse {
        $rawEmail = $request->input('email');
        $email = is_string($rawEmail) ? trim($rawEmail) : '';

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => __('swift-auth::email.verification_invalid_email')], 400);
        }

        /** @var array{attempts?:int,decay_seconds?:int}|mixed $ipRateLimit */
        $ipRateLimit = config('swift-auth.email_verification.ip_rate_limit', []);
        $ipAttempts = (int) ($ipRateLimit['attempts'] ?? 5);
        $ipDecay = (int) ($ipRateLimit['decay_seconds'] ?? 60);

        // Rate limit per IP to prevent abuse
        $ipLimiter = 'email-verification:ip:' . $request->ip();
        if (
            RateLimiter::tooManyAttempts(
                $ipLimiter,
                $ipAttempts,
            )
        ) {
            $seconds = RateLimiter::availableIn($ipLimiter);
            logger()->warning(
                'swift-auth.email-verification.ip-rate-limit-exceeded',
                [
                    'ip' => $request->ip(),
                    'email_hash' => hash('sha256', $email),
                ],
            );

            return response()->json(['message' => __('swift-auth::email.verification_too_many_requests', ['seconds' => $seconds])], 429);
        }

        $rateLimitKey = 'email-verification:' . hash('sha256', $email);
        /** @var array{attempts?:int,decay_seconds?:int}|mixed $rateLimitConfig */
        $rateLimitConfig = config('swift-auth.email_verification.resend_rate_limit', []);
        $attempts = (int) ($rateLimitConfig['attempts'] ?? 3);
        $decaySeconds = (int) ($rateLimitConfig['decay_seconds'] ?? 300);

        if (
            RateLimiter::tooManyAttempts(
                $rateLimitKey,
                $attempts,
            )
        ) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            logger()->warning(
                'swift-auth.email-verification.rate-limit-exceeded',
                [
                    'email_hash' => hash('sha256', $email),
                    'ip' => $request->ip(),
                ],
            );

            return response()->json(['message' => __('swift-auth::email.verification_rate_limit', ['seconds' => $seconds])], 429);
        }

        $user = User::where(
            'email',
            $email,
        )->first();

        if (!$user) {
            RateLimiter::hit(
                $rateLimitKey,
                $decaySeconds,
            );
            return response()->json(['message' => __('swift-auth::email.verification_sent'), 'data' => null], 200);
        }

        if ($user->email_verified_at) {
            return response()->json(['message' => __('swift-auth::email.verification_already_verified')], 400);
        }

        $token = Str::random(64);
        $user->email_verification_token = hash('sha256', $token);
        $user->email_verification_sent_at = now();
        $user->save();

        $result = $notificationService->sendEmailVerification(
            $email,
            $token,
        );

        if (!$result->success) {
            return response()->json(['message' => __('swift-auth::email.verification_failed')], 500);
        }

        RateLimiter::hit(
            $rateLimitKey,
            $decaySeconds,
        );
        RateLimiter::hit(
            $ipLimiter,
            $ipDecay,
        );

        logger()->info(
            'swift-auth.email-verification.sent',
            [
                'user_id' => $user->id_user,
                'email_hash' => hash('sha256', $email),
                'message_id' => $result->messageId,
                'ip' => $request->ip(),
            ],
        );

        return response()->json([
            'message' => __('swift-auth::email.verification_sent'),
            'data' => null,
        ], 200);
    }

    /**
     * Verifies email with token.
     *
     * Validates token, checks TTL, and marks email as verified on success.
     *
     * @param  Request       $request  HTTP request with token and email.
     * @return JsonResponse            Success or error response.
     */
    public function verify(Request $request): JsonResponse
    {
        $tokenRaw = $request->route('token');
        $token = is_string($tokenRaw) ? $tokenRaw : '';
        $rawQueryEmail = $request->query('email');
        $email = is_string($rawQueryEmail) ? trim($rawQueryEmail) : '';

        if ($token === '' || $email === '') {
            return response()->json(['message' => __('swift-auth::email.verification_invalid')], 400);
        }

        $hashedToken = hash('sha256', $token);
        $user = User::where(
            'email',
            $email,
        )
            ->where(
                'email_verification_token',
                $hashedToken,
            )
            ->first();

        if (!$user) {
            logger()->warning(
                'swift-auth.email-verification.invalid-token',
                [
                    'email' => $email,
                    'ip' => $request->ip(),
                ],
            );

            return response()->json(['message' => __('swift-auth::email.verification_invalid')], 400);
        }

        $ttl = (int) config('swift-auth.email_verification.token_ttl', 86400);
        $sentAt = $user->email_verification_sent_at;
        if ($sentAt !== null && $sentAt->addSeconds($ttl)->isPast()) {
            logger()->warning(
                'swift-auth.email-verification.token-expired',
                [
                    'user_id' => $user->id_user,
                    'email' => $email,
                    'sent_at' => $sentAt,
                ],
            );

            return response()->json(['message' => __('swift-auth::email.verification_expired')], 400);
        }

        $user->email_verified_at = now();
        $user->email_verification_token = null;
        $user->email_verification_sent_at = null;
        $user->save();

        logger()->info(
            'swift-auth.email-verification.verified',
            [
                'user_id' => $user->id_user,
                'email' => $email,
                'ip' => $request->ip(),
            ],
        );

        return response()->json(['message' => __('swift-auth::email.verification_success')], 200);
    }
}
