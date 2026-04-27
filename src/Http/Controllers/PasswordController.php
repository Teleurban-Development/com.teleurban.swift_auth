<?php

/**
 * Handles password reset flows for SwiftAuth consumers.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Controllers
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 * @link      https://github.com/EquidnaMX/swift_auth
 */

namespace Equidna\SwiftAuth\Http\Controllers;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Equidna\SwiftAuth\Models\PasswordResetToken;
use Equidna\SwiftAuth\Models\User;
use Equidna\SwiftAuth\Classes\Notifications\NotificationService;
use Equidna\SwiftAuth\Classes\Auth\Services\PasswordPolicy;
use Equidna\SwiftAuth\Classes\Auth\Traits\ChecksRateLimits;
use Equidna\SwiftAuth\Support\Traits\SelectiveRender;
use Equidna\Toolkit\Exceptions\BadRequestException;
use Equidna\Toolkit\Exceptions\NotFoundException;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
use Equidna\Toolkit\Helpers\ResponseHelper;

/**
 * Coordinates SwiftAuth password reset UX, from request to completion.
 *
 * Renders multi-context views and interacts with reset tokens plus notification mailers.
 */
class PasswordController extends Controller
{
    use ChecksRateLimits;
    use SelectiveRender;

    /**
     * Shows the password reset request form.
     *
     * @param  Request        $request  HTTP request context.
     * @return View|Responsable         Blade or Inertia response.
     */
    public function showRequestForm(Request $request): View|Responsable
    {
        return $this->render(
            'swift-auth::password.email',
            'SwiftAuth/Password/Request',
        );
    }

    /**
     * Sends password reset instructions to the provided email.
     *
     * Enforces rate limiting per email and IP to prevent abuse, generates a secure token,
     * and dispatches reset email via notification service.
     *
     * @param  Request                    $request              HTTP request with the email address.
     * @param  NotificationService        $notificationService  Email notification service.
     * @return RedirectResponse|JsonResponse                    Context-aware success response.
     * @throws BadRequestException                              When email dispatch fails.
     */
    public function sendResetLink(
        Request $request,
        NotificationService $notificationService,
    ): RedirectResponse|JsonResponse {
        $data = $request->validate(['email' => 'required|email']);

        $email = strtolower($data['email']);

        /** @var array{attempts?:int,decay_seconds?:int}|mixed $rateConfig */
        $rateConfig = config('swift-auth.password_reset_rate_limit', []);
        $attempts = (int) ($rateConfig['attempts'] ?? 3);
        $decay = (int) ($rateConfig['decay_seconds'] ?? 300);

        // Limit requests per-target (email) to prevent abuse and enumeration.
        $limiterKey = 'password-reset:email:' . hash('sha256', $email);

        // Additional soft limit per-IP to curtail mass scanning.
        $ipKey = 'password-reset:ip:' . $request->ip();

        // SECURITY: email-specific limit is intentionally silent to prevent enumeration.
        $emailLimitExceeded = RateLimiter::tooManyAttempts($limiterKey, $attempts);

        // IP-level protection: high threshold to reduce noise but stop large scans
        $ipThreshold = max(50, $attempts * 10);
        try {
            $this->checkRateLimit(
                $ipKey,
                $ipThreshold,
                'Too many requests from this network.'
            );
        } catch (UnauthorizedException $e) {
            $availableIn = $this->rateLimitAvailableIn($ipKey);
            return response()->json([
                'message' => $e->getMessage() . ' seconds.'
            ], 429);
        }

        // Count attempt early to prevent enumeration races
        $this->hitRateLimit($ipKey, $decay);

        if (!$emailLimitExceeded) {
            $this->hitRateLimit($limiterKey, $decay);
        }

        // Generate raw token and hash for storage
        $rawToken = Str::random(64);
        $hashedToken = hash('sha256', $rawToken);

        // Only create token if user exists, but don't reveal this in response
        $userExists = User::where('email', $email)->exists();

        if ($userExists) {
            if (!$emailLimitExceeded) {
                PasswordResetToken::updateOrCreate(
                    ['email' => $email],
                    ['token' => $hashedToken, 'created_at' => now()]
                );

                try {
                    $messageId = $notificationService->sendPasswordReset($email, $rawToken);

                    logger()->info('swift-auth.password-reset.email-sent', [
                        'email_hash' => hash('sha256', $email),
                        'message_id' => $messageId,
                        'ip' => $request->ip(),
                    ]);
                } catch (\RuntimeException $e) {
                    logger()->error('swift-auth.password-reset.send-failed', [
                        'email_hash' => hash('sha256', $email),
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                logger()->warning('swift-auth.password-reset.email-rate-limit', [
                    'email_hash' => hash('sha256', $email),
                    'ip' => $request->ip(),
                ]);
            }
        } else {
            logger()->info('swift-auth.password-reset.unknown-email', [
                'email_hash' => hash('sha256', $email),
                'ip' => $request->ip(),
            ]);
        }

        return ResponseHelper::success(
            message: 'Password reset instructions sent (if the email exists).',
            data: ['email' => $email],
            forward_url: route('swift-auth.password.request.sent'),
        );
    }

    /**
     * Shows the reset form populated with the token.
     *
     * @param  Request       $request  HTTP request context.
     * @param  string        $token    Reset token value.
     * @return View|Responsable        Blade or Inertia response.
     */
    public function showResetForm(
        Request $request,
        string $token,
    ): View|Responsable {
        return $this->render(
            'swift-auth::password.reset',
            'SwiftAuth/Password/Reset',
            [
                'token' => $token,
                'email' => $request->input('email'),
            ],
        );
    }

    /**
     * Shows the confirmation page after emailing reset instructions.
     *
     * @param  Request       $request  HTTP request context.
     * @return View|Responsable        Blade or Inertia response.
     */
    public function showRequestSent(Request $request): View|Responsable
    {
        return $this->render(
            'swift-auth::password.request_sent',
            'SwiftAuth/Password/RequestSent',
        );
    }

    /**
     * Updates the password when the token is valid.
     *
     * @param  Request                   $request  HTTP request containing token, email, and password.
     * @return RedirectResponse|JsonResponse       Context-aware success response.
     * @throws BadRequestException                 When token validation fails.
     */
    public function resetPassword(Request $request): RedirectResponse|JsonResponse
    {
        $passwordRules = PasswordPolicy::rules();

        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => array_merge(
                ['required', 'string', 'confirmed'],
                $passwordRules,
            ),
        ]);

        // Protect verification endpoint from brute-force attempts.
        $verifyLimiter = 'password-reset:verify:' . hash('sha256', strtolower($data['email']));
        $verifyAttempts = (int) config('swift-auth.password_reset_verify_attempts', 10);
        $verifyDecay = (int) config('swift-auth.password_reset_verify_decay_seconds', 3600);

        if (RateLimiter::tooManyAttempts($verifyLimiter, $verifyAttempts)) {
            $availableIn = RateLimiter::availableIn($verifyLimiter);
            throw new BadRequestException(
                'Too many verification attempts. Please try again later.'
            );
        }

        /** @var array{email:string,token:string,password:string,password_confirmation:string} $data */
        $reset = PasswordResetToken::where('email', strtolower($data['email']))->first();

        // Use constant-time comparison to prevent timing attacks.
        // The stored token is a sha256 hash of the raw token, so hash the
        // supplied token before comparison to avoid mismatches.
        $expectedToken = hash('sha256', $data['token']);
        if (!$reset || !hash_equals($reset->token, $expectedToken)) {
            // Increment the verify limiter to slow down brute-force.
            RateLimiter::hit($verifyLimiter, $verifyDecay);
            throw new BadRequestException('Invalid or expired reset token.');
        }

        // Enforce TTL
        $ttl = (int) config('swift-auth.password_reset_ttl', 900);
        /** @var \Illuminate\Support\Carbon|null $createdAt */
        $createdAt = $reset->created_at;
        if (!$createdAt || $createdAt->diffInSeconds() > $ttl) {
            // remove expired token and reject
            $reset->delete();
            throw new BadRequestException('Invalid or expired reset token.');
        }

        $user = User::where('email', strtolower($data['email']))->first();

        if (!$user) {
            // Token exists but user doesn't - cleanup and reject with uniform message
            $reset->delete();
            throw new BadRequestException('Invalid or expired reset token.');
        }

        $driver = config('swift-auth.hash_driver');
        $driver = is_string($driver) ? $driver : null;
        if ($driver) {
            /** @var \Illuminate\Contracts\Hashing\Hasher $hasher */
            $hasher = Hash::driver($driver);
            $hashed = $hasher->make($data['password']);
        } else {
            $hashed = Hash::make($data['password']);
        }

        $user->update([
            'password' => $hashed
        ]);

        logger()->info('swift-auth.password-reset.completed', [
            'user_id' => $user->getKey(),
            'email_hash' => hash('sha256', strtolower($user->email)),
            'ip' => $request->ip(),
        ]);

        $reset->delete();
        // Successful verification: clear the verify limiter for this target
        RateLimiter::clear($verifyLimiter);
        return ResponseHelper::success(
            message: 'Password updated successfully.',
            data: [
                'user_id' => $user->getKey(),
            ],
            forward_url: route('swift-auth.login.form'),
        );
    }
}
