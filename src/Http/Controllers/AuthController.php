<?php

/**
 * Exposes SwiftAuth login/logout flows for Laravel apps.
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
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Equidna\SwiftAuth\Classes\Auth\Services\AccountLockoutService;
use Equidna\SwiftAuth\Classes\Auth\Traits\ChecksRateLimits;
use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Facades\SwiftAuth;
use Equidna\SwiftAuth\Http\Requests\LoginRequest;
use Equidna\SwiftAuth\Support\Traits\SelectiveRender;
use Equidna\Toolkit\Exceptions\UnauthorizedException;
use Equidna\Toolkit\Helpers\ResponseHelper;

/**
 * Orchestrates SwiftAuth authentication flows across login/logout endpoints.
 *
 * Presents blade or Inertia views as needed and emits context-aware toolkit responses.
 */
class AuthController extends Controller
{
    use ChecksRateLimits;
    use SelectiveRender;

    /**
     * Shows the login form view.
     *
     * @param  Request        $request  HTTP request with context info.
     * @return View|Responsable         Blade or Inertia response.
     */
    public function showLoginForm(Request $request): View|Responsable
    {
        return $this->render(
            'swift-auth::login',
            'SwiftAuth/Login',
        );
    }

    /**
     * Authenticates the user using SwiftAuth.
     *
     * Enforces rate limiting per email and IP, checks account lockout status, validates credentials,
     * and manages login attempt tracking with automatic account lockout.
     *
     * @param  \Equidna\SwiftAuth\Http\Requests\LoginRequest $request         Validated login request.
     * @param  UserRepositoryInterface                          $userRepository  User data access layer.
     * @param  AccountLockoutService                            $lockoutService  Lockout management service.
     * @return RedirectResponse|JsonResponse                                   Context-aware success response.
     * @throws UnauthorizedException                                           When credentials invalid or account locked.
     */
    public function login(
        LoginRequest $request,
        UserRepositoryInterface $userRepository,
        AccountLockoutService $lockoutService
    ): RedirectResponse|JsonResponse {
        /** @var array{email:string,password:string} $credentials */
        $credentials = $request->validated();

        $rateLimiterKeys = $this->checkLoginRateLimits($request, $credentials['email']);

        $user = $this->validateCredentials(
            $credentials,
            $userRepository,
            $lockoutService,
            $request,
            $rateLimiterKeys
        );

        // Reset failed login attempts on successful login
        $lockoutService->resetAttempts($user);
        $this->clearRateLimit($rateLimiterKeys['email']);

        $mfaResponse = $this->handleMfaChallenge($request, $user);
        if ($mfaResponse !== null) {
            return $mfaResponse;
        }

        return $this->completeLogin($request, $user);
    }

    /**
     * Logs out the current user and clears the session.
     *
     * @param  Request                    $request  HTTP request carrying the session.
     * @return RedirectResponse|JsonResponse        Context-aware success response.
     */
    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        $userId = SwiftAuth::id();

        SwiftAuth::logout();

        logger()->info('User logged out', [
            'user_id' => $userId,
            'ip' => $request->ip(),
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ResponseHelper::success(
            message: 'Logged out successfully.',
            data: null,
            forward_url: route('swift-auth.login.form'),
        );
    }

    /**
     * Checks rate limits for email and IP.
     *
     * @param  Request $request  HTTP request with IP context.
     * @param  string  $email    Email address being authenticated.
     * @return array{email:string,ip:string,emailDecay:int,ipDecay:int}  Rate limiter keys and decay values.
     * @throws UnauthorizedException                                     When rate limit exceeded.
     */
    private function checkLoginRateLimits(Request $request, string $email): array
    {
        $loginRateLimit = config('swift-auth.login_rate_limits', []);
        $emailLimiterConfig = is_array($loginRateLimit['email'] ?? null)
            ? $loginRateLimit['email']
            : [];
        $ipLimiterConfig = is_array($loginRateLimit['ip'] ?? null)
            ? $loginRateLimit['ip']
            : [];

        $emailKey = 'login:email:' . hash('sha256', strtolower($email));
        $ipKey = 'login:ip:' . $request->ip();

        $emailAttempts = (int) ($emailLimiterConfig['attempts'] ?? 5);
        $emailDecay = (int) ($emailLimiterConfig['decay_seconds'] ?? 300);

        $this->checkRateLimit(
            $emailKey,
            $emailAttempts,
            'Too many login attempts.'
        );

        $ipAttempts = (int) ($ipLimiterConfig['attempts'] ?? 20);
        $ipDecay = (int) ($ipLimiterConfig['decay_seconds'] ?? 300);

        $this->checkRateLimit(
            $ipKey,
            $ipAttempts,
            'Too many login attempts from this network.'
        );

        return [
            'email' => $emailKey,
            'ip' => $ipKey,
            'emailDecay' => $emailDecay,
            'ipDecay' => $ipDecay,
        ];
    }

    /**
     * Validates user credentials and enforces account lockout policies.
     *
     * @param  array{email:string,password:string}                       $credentials      User credentials.
     * @param  UserRepositoryInterface                                   $userRepository   User data layer.
     * @param  AccountLockoutService                                     $lockoutService   Lockout service.
     * @param  Request                                                   $request          HTTP request.
     * @param  array{email:string,ip:string,emailDecay:int,ipDecay:int} $rateLimiterKeys  Rate limiter keys.
     * @return \Equidna\SwiftAuth\Models\User                                             Authenticated user.
     * @throws UnauthorizedException                                                      When credentials invalid or locked.
     */
    private function validateCredentials(
        array $credentials,
        UserRepositoryInterface $userRepository,
        AccountLockoutService $lockoutService,
        Request $request,
        array $rateLimiterKeys
    ): \Equidna\SwiftAuth\Models\User {
        $user = $userRepository->findByEmail($credentials['email']);

        if ($user) {
            $lockoutService->refreshAttemptsAfterInactivity($user);
        }

        // Check if account is locked
        if ($user && $lockoutService->isLocked($user)) {
            $remainingMinutes = $lockoutService->getRemainingLockoutMinutes($user);

            logger()->warning('swift-auth.login.account-locked', [
                'user_id' => $user->getKey(),
                'email' => $user->email,
                'locked_until' => $user->locked_until,
                'ip' => $request->ip(),
            ]);

            throw new UnauthorizedException(
                "Account is temporarily locked. Please try again in {$remainingMinutes} minutes."
            );
        }

        // Always perform hash check to prevent timing attacks (constant-time comparison)
        // Use dummy bcrypt hash when user doesn't exist to maintain consistent timing
        $dummyHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // bcrypt of 'password'
        $passwordToCheck = $user ? $user->password : $dummyHash;

        $driver = config('swift-auth.hash_driver');
        $driver = is_string($driver) ? $driver : null;
        if ($driver) {
            /** @var \Illuminate\Contracts\Hashing\Hasher $hasher */
            $hasher = Hash::driver($driver);
            $valid = $hasher->check($credentials['password'], $passwordToCheck);
        } else {
            $valid = Hash::check($credentials['password'], $passwordToCheck);
        }

        if (!$user || !$valid) {
            $this->handleFailedLogin($user, $credentials, $lockoutService, $request, $rateLimiterKeys);
        }

        return $user;
    }

    /**
     * Handles failed login attempts with rate limiting and lockout tracking.
     *
     * @param  \Equidna\SwiftAuth\Models\User|null                       $user             User instance or null.
     * @param  array{email:string,password:string}                       $credentials      User credentials.
     * @param  AccountLockoutService                                     $lockoutService   Lockout service.
     * @param  Request                                                   $request          HTTP request.
     * @param  array{email:string,ip:string,emailDecay:int,ipDecay:int} $rateLimiterKeys  Rate limiter keys.
     * @return never
     * @throws UnauthorizedException
     */
    private function handleFailedLogin(
        ?\Equidna\SwiftAuth\Models\User $user,
        array $credentials,
        AccountLockoutService $lockoutService,
        Request $request,
        array $rateLimiterKeys
    ): never {
        // Record failed attempt and trigger lockout if threshold reached
        if ($user) {
            $lockoutService->refreshAttemptsAfterInactivity($user);

            $ip = (string) ($request->ip() ?? '');
            $wasLocked = $lockoutService->recordFailedAttempt($user, $ip);

            if ($wasLocked) {
                throw new UnauthorizedException(
                    'Account has been locked due to too many failed login attempts.'
                );
            }
        }

        // Increment rate limiter on failed login
        $this->hitRateLimit($rateLimiterKeys['email'], $rateLimiterKeys['emailDecay']);
        $this->hitRateLimit($rateLimiterKeys['ip'], $rateLimiterKeys['ipDecay']);

        logger()->warning('swift-auth.login.failed', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        throw new UnauthorizedException('Invalid credentials.');
    }

    /**
     * Handles MFA challenge initiation when enabled.
     *
     * @param  Request                           $request  HTTP request.
     * @param  \Equidna\SwiftAuth\Models\User   $user     Authenticated user.
     * @return JsonResponse|RedirectResponse|null         MFA response or null if not required.
     */
    private function handleMfaChallenge(
        Request $request,
        \Equidna\SwiftAuth\Models\User $user
    ): JsonResponse|RedirectResponse|null {
        $mfaConfig = config('swift-auth.mfa', []);

        if (!$this->shouldRequestMfa($mfaConfig)) {
            return null;
        }

        $driver = $this->resolveMfaDriver($mfaConfig);
        $verificationUrl = (string) ($mfaConfig['verification_url'] ?? '');

        SwiftAuth::startMfaChallenge(
            user: $user,
            driver: $driver,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        logger()->info('swift-auth.login.mfa-required', [
            'user_id' => $user->getKey(),
            'driver' => $driver,
            'ip' => $request->ip(),
        ]);

        return ResponseHelper::success(
            message: 'Additional verification required.',
            data: [
                'mfa_required' => true,
                'driver' => $driver,
                'verification_url' => $verificationUrl,
                'user_id' => $user->getKey(),
            ],
        );
    }

    /**
     * Completes the login flow and handles session eviction.
     *
     * @param  Request                         $request  HTTP request.
     * @param  \Equidna\SwiftAuth\Models\User $user     Authenticated user.
     * @return JsonResponse|RedirectResponse            Success response with optional eviction data.
     */
    private function completeLogin(
        Request $request,
        \Equidna\SwiftAuth\Models\User $user
    ): JsonResponse|RedirectResponse {
        logger()->info('swift-auth.login.success', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $remember = (bool) ($request->boolean('remember') || $request->boolean('remember_me'));

        $loginResult = SwiftAuth::login(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            deviceName: (string) $request->header('X-Device-Name', ''),
            remember: $remember,
        );

        $evictedSessionIds = (array) ($loginResult['evicted_session_ids'] ?? []);
        $evictionPolicy = config('swift-auth.session_limits.eviction', null);

        $response = ResponseHelper::success(
            message: 'Login successful.',
            data: [
                'user_id' => $user->getKey(),
                'evicted_session_ids' => $loginResult['evicted_session_ids'] ?? [],
            ],
            forward_url: Config::get('swift-auth.success_url'),
        );

        if (!empty($evictedSessionIds)) {
            $response = $this->attachEvictionData($response, $evictedSessionIds, $evictionPolicy);
        }

        return $response;
    }

    /**
     * Attaches session eviction data to the response.
     *
     * @param  JsonResponse|RedirectResponse $response           Original response.
     * @param  array<int, mixed>             $evictedSessionIds  Evicted session identifiers.
     * @param  string|null                   $evictionPolicy     Eviction policy name.
     * @return JsonResponse|RedirectResponse                     Response with eviction data.
     */
    private function attachEvictionData(
        JsonResponse|RedirectResponse $response,
        array $evictedSessionIds,
        ?string $evictionPolicy
    ): JsonResponse|RedirectResponse {
        $evictionMessage = $this->getEvictionMessage($evictionPolicy);

        if ($response instanceof JsonResponse) {
            /** @var array{status?:mixed,message?:mixed,data?:array<string,mixed>,forward_url?:mixed} $payload */
            $payload = $response->getData(true);

            $payload['data'] = ($payload['data'] ?? []) + [
                'evicted_session_ids' => $evictedSessionIds,
                'eviction_policy' => $evictionPolicy,
            ];

            if ($evictionMessage !== null) {
                $payload['data']['eviction_message'] = $evictionMessage;
            }

            $response->setData($payload);
        }

        if ($response instanceof RedirectResponse) {
            session()->flash('evicted_session_ids', $evictedSessionIds);
            session()->flash('eviction_policy', $evictionPolicy);

            if ($evictionMessage !== null) {
                session()->flash('eviction_message', $evictionMessage);
            }
        }

        return $response;
    }

    /**
     * Determines whether an MFA challenge should be triggered.
     *
     * @param  array<string, mixed> $mfaConfig  MFA configuration values.
     * @return bool
     */
    private function shouldRequestMfa(array $mfaConfig): bool
    {
        return (bool) ($mfaConfig['enabled'] ?? false);
    }

    /**
     * Resolves the MFA driver name from configuration.
     *
     * @param  array<string, mixed> $mfaConfig  MFA configuration values.
     * @return string
     */
    private function resolveMfaDriver(array $mfaConfig): string
    {
        $driver = (string) ($mfaConfig['driver'] ?? 'otp');

        return in_array($driver, ['otp', 'webauthn'], true)
            ? $driver
            : 'otp';
    }

    private function getEvictionMessage(?string $policy): ?string
    {
        return match ($policy) {
            'newest' => __('swift-auth::session.evicted_newest'),
            'oldest' => __('swift-auth::session.evicted_oldest'),
            default => null,
        };
    }
}
