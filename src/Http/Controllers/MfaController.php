<?php

/**
 * Handles multi-factor authentication verification flows.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Http\Controllers
 * @author    SwiftAuth Contributors
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Http\Controllers;

use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Facades\SwiftAuth;
use Equidna\SwiftAuth\Classes\Auth\Services\MfaService;
use Equidna\Toolkit\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/**
 * Finalizes MFA challenges for OTP and WebAuthn drivers.
 */
class MfaController extends Controller
{
    /**
     * Verifies an OTP-based MFA challenge and finalizes login.
     */
    public function verifyOtp(
        Request $request,
        UserRepositoryInterface $userRepository,
        MfaService $mfaService,
    ): JsonResponse|RedirectResponse {
        $otp = $request->input('otp');

        if (!is_string($otp) || $otp === '') {
            return ResponseHelper::badRequest(message: 'OTP code is required.');
        }

        return $this->finalizeMfa(
            $request,
            $userRepository,
            $mfaService,
            'otp',
            ['otp' => $otp],
        );
    }

    /**
     * Verifies a WebAuthn-based MFA challenge and finalizes login.
     */
    public function verifyWebAuthn(
        Request $request,
        UserRepositoryInterface $userRepository,
        MfaService $mfaService,
    ): JsonResponse|RedirectResponse {
        $credential = $request->input('credential');

        if (!is_array($credential) || $credential === []) {
            return ResponseHelper::badRequest(message: 'WebAuthn credential is required.');
        }

        return $this->finalizeMfa(
            $request,
            $userRepository,
            $mfaService,
            'webauthn',
            ['credential' => $credential],
        );
    }

    /**
     * Runs the configured MFA verification flow and authenticates the user on success.
     *
     * @param  Request                   $request         HTTP request context.
     * @param  UserRepositoryInterface   $userRepository  Data access for pending user lookup.
     * @param  string                    $method          MFA method being verified (otp|webauthn).
     * @param  array<string,mixed>       $payload         Payload forwarded to verification endpoint.
     * @return JsonResponse|RedirectResponse              ResponseHelper-wrapped response.
     */
    protected function finalizeMfa(
        Request $request,
        UserRepositoryInterface $userRepository,
        MfaService $mfaService,
        string $method,
        array $payload,
    ): JsonResponse|RedirectResponse {
        $pendingUserId = $mfaService->getPendingUserId();
        $pendingMethod = $mfaService->getPendingDriver();

        if (!$pendingUserId || ($pendingMethod && $pendingMethod !== $method)) {
            return ResponseHelper::unauthorized(message: 'No pending MFA challenge.');
        }

        if (!$mfaService->isPendingChallengeValid()) {
            $mfaService->clearPendingChallenge();
            logger()->warning('swift-auth.mfa.challenge-expired', [
                'user_id' => $pendingUserId,
                'method' => $method,
            ]);

            return ResponseHelper::unauthorized(message: 'MFA challenge expired. Please log in again.');
        }

        $pendingUserIdInt = is_int($pendingUserId) ? $pendingUserId : (int) $pendingUserId;
        $user = $userRepository->findById($pendingUserIdInt);

        if (!$user) {
            return ResponseHelper::unauthorized(message: 'MFA user not found.');
        }

        /** @var array{verification_url?:string,driver?:string}|mixed $configRaw */
        $configRaw = config("swift-auth.mfa.{$method}", []);
        $config = is_array($configRaw) ? $configRaw : [];
        $verificationUrl = is_string($config['verification_url'] ?? null)
            ? $config['verification_url']
            : '';

        if ($verificationUrl === '') {
            return ResponseHelper::error(message: 'MFA verification endpoint not configured.');
        }

        $driver = is_string($config['driver'] ?? null) ? $config['driver'] : $method;

        $verificationResponse = Http::asJson()->timeout(5)->connectTimeout(2)->post(
            $verificationUrl,
            array_merge(
                $payload,
                [
                    'user_id' => $user->getKey(),
                    'method' => $method,
                    'driver' => $driver,
                ],
            ),
        );

        $valid = $verificationResponse->successful()
            && ($verificationResponse->json('valid') === true);

        if (!$valid) {
            return ResponseHelper::unauthorized(message: 'Invalid MFA verification.');
        }

        $mfaService->clearPendingChallenge();
        SwiftAuth::login($user);
        $request->session()->regenerate();

        $successUrl = config('swift-auth.success_url');
        $forwardUrl = is_string($successUrl) ? $successUrl : null;

        return ResponseHelper::success(
            message: 'MFA verification successful.',
            data: [
                'user_id' => $user->getKey(),
            ],
            forward_url: $forwardUrl,
        );
    }
}
