<?php

/**
 * Handles WebAuthn (Passkey) registration and authentication.
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
use Equidna\SwiftAuth\Models\User;
use Equidna\Toolkit\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravel\Sanctum\Contracts\HasApiTokens;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\Facades\WebAuthn;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

/**
 * Manages WebAuthn credentials for mobile and web clients.
 */
class WebAuthnController extends Controller
{
    /**
     * Generates attestation options for registering a new specific Passkey.
     */
    public function registerOptions(Request $request): JsonResponse|RedirectResponse
    {
        // User must be logged in to register a new Passkey
        $user = $request->user();

        if (!$user) {
            return ResponseHelper::unauthorized(message: 'User must be authenticated to register credentials.');
        }

        // Generate options using the package facade
        return response()->json(
            WebAuthn::createAttestationOptions(
                user: $user,
                domain: $request->getHost()
            )
        );
    }

    /**
     * Verifies the attestation response and stores the credential.
     */
    public function register(AttestedRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $request->save();

            return ResponseHelper::success(message: 'Biometric credential registered successfully.');
        } catch (\Throwable $e) {
            logger()->error('swift-auth.webauthn.register-failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return ResponseHelper::error(
                message: 'Credential registration failed. Please try again.'
            );
        }
    }

    /**
     * Generates assertion options for logging in.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        // For passwordless/user-nameless flow, we don't need a user instance.
        // However, if the user provides an email/username first, we can scope it.
        $email = $request->input('email');

        if ($email) {
            /** @var User|null $user */
            $user = User::where('email', $email)->first();
            if ($user && $user instanceof WebAuthnAuthenticatable) {
                return response()->json(WebAuthn::createAssertionOptions($user));
            }
        }

        // Check if package allows user-nameless login options generation
        return response()->json(WebAuthn::createAssertionOptions());
    }

    /**
     * Verifies the assertion response and logs the user in.
     */
    public function login(AssertedRequest $request): JsonResponse|RedirectResponse
    {
        try {
            if ($request->login()) {
                $user = $request->user();

                // Issue Sanctum Token for API/Mobile usage if device name is present
                $token = null;
                if ($request->filled('device_name') && method_exists($user, 'createToken')) {
                    /** @var HasApiTokens $user */
                    $token = $user->createToken($request->input('device_name'))->plainTextToken;
                }

                return ResponseHelper::success(
                    message: 'Authenticated via Biometrics.',
                    data: [
                        'user' => $user,
                        'token' => $token,
                        'redirect_url' => config('swift-auth.success_url')
                    ]
                );
            }

            return ResponseHelper::unauthorized(message: 'Biometric authentication failed.');
        } catch (\Throwable $e) {
            logger()->error('swift-auth.webauthn.login-failed', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);
            return ResponseHelper::error(
                message: 'Authentication failed. Please try again.'
            );
        }
    }
}
