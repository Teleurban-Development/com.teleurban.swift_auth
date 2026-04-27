<?php

/**
 * Manages "Remember Me" token lifecycle and cookie handling.
 *
 * PHP 8.2+
 *
 * @package   Equidna\SwiftAuth\Classes\Auth\Services
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\SwiftAuth\Classes\Auth\Services;

use Carbon\CarbonImmutable;
use Equidna\SwiftAuth\Classes\Auth\DTO\RememberToken as RememberTokenDTO;
use Equidna\SwiftAuth\Classes\Users\Contracts\UserRepositoryInterface;
use Equidna\SwiftAuth\Models\RememberToken;
use Equidna\SwiftAuth\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

/**
 * Manages "Remember Me" tokens and their persistence.
 */
class RememberMeService
{
    protected string $cookieName;

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected TokenMetadataValidator $metadataValidator,
    ) {
        $this->cookieName = (string) config('swift-auth.remember_me.cookie_name', 'swift_auth_remember');
    }

    public function attemptLogin(): ?User
    {
        if (!$this->shouldIssueToken()) {
            return null;
        }

        $cookie = Cookie::get($this->cookieName);

        if (!is_string($cookie) || $cookie === '') {
            return null;
        }

        [$selector, $validator] = $this->splitCookie($cookie);

        if ($selector === null || $validator === null) {
            $this->forgetCookie();
            return null;
        }

        try {
            $token = RememberToken::query()
                ->where('selector', $selector)
                ->first();
        } catch (QueryException $exception) {
            logger()->warning('swift-auth.remember.load_failed', [
                'selector' => $selector,
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        if (!$token || $this->isTokenExpired($token)) {
            $token?->delete();
            $this->forgetCookie();
            return null;
        }

        $expected = hash('sha256', $validator);

        if (!hash_equals($token->hashed_token, $expected)) {
            $token->delete();
            $this->forgetCookie();
            return null;
        }

        $user = $this->userRepository->findById((int) $token->id_user);

        if (!$user) {
            $token->delete();
            $this->forgetCookie();
            return null;
        }

        $request = function_exists('request') ? request() : null;
        if ($request) {
            $validMetadata = $this->metadataValidator->validateWithLogging([
                'ip' => $token->ip_address,
                'user_agent' => $token->user_agent,
                'device_name' => $token->device_name,
                'user_id' => $token->id_user,
            ], $request);

            if (!$validMetadata) {
                // Suspicious activity - maybe revoke token?
                // The policy might dictate deletion.
                // For now, let's treat it as invalid login but maybe keep token or delete it?
                // The test implies `attemptRememberLogin` returns false.
                // Usually security mismatch -> revoke token to be safe.
                $token->delete();
                $this->forgetCookie();
                return null;
            }
        }


        $shouldRotate = (bool) config('swift-auth.remember_me.rotate_on_use', true);

        if ($shouldRotate) {
            $token->delete();
        } else {
            $token->last_used_at = CarbonImmutable::now();
            $token->save();
        }

        // Caller handles re-issuing token if rotated
        return $user;
    }

    public function queueToken(
        User $user,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $deviceName,
        ?string $platform,
        ?string $browser,
    ): void {
        try {
            $selector = Str::random(20);
            $validator = Str::random(64);
            $hashedValidator = hash('sha256', $validator);
            $ttlSeconds = (int) config('swift-auth.remember_me.ttl_seconds', 1209600);
            $expiresAt = CarbonImmutable::now()->addSeconds($ttlSeconds);

            RememberToken::query()->create([
                'id_user' => $user->getKey(),
                'selector' => $selector,
                'hashed_token' => $hashedValidator,
                'expires_at' => $expiresAt,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'device_name' => $deviceName,
                'platform' => $platform,
                'browser' => $browser,
            ]);

            $cookieValue = $selector . ':' . $validator;
            $minutes = (int) ceil($ttlSeconds / 60);

            $cookie = Cookie::make(
                $this->cookieName,
                $cookieValue,
                minutes: $minutes,
                path: (string) config('swift-auth.remember_me.path', '/'),
                domain: config('swift-auth.remember_me.domain', null),
                secure: (bool) config('swift-auth.remember_me.secure', true),
                httpOnly: true,
                raw: false,
                sameSite: (string) config('swift-auth.remember_me.same_site', 'lax'),
            );

            Cookie::queue($cookie);
        } catch (QueryException $exception) {
            logger()->warning('swift-auth.remember.issue_failed', [
                'user_id' => $user->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function deleteToken(string $cookieValue): void
    {
        [$selector, $validator] = $this->splitCookie($cookieValue);

        if ($selector === null || $validator === null) {
            $this->forgetCookie();
            return;
        }

        try {
            RememberToken::query()
                ->where('selector', $selector)
                ->delete();
        } catch (QueryException $exception) {
            logger()->warning('swift-auth.remember.delete_failed', [
                'selector' => $selector,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->forgetCookie();
    }

    public function revokeForUser(int $userId): int
    {
        return RememberToken::query()
            ->where('id_user', $userId)
            ->delete();
    }

    public function forgetCookie(): void
    {
        Cookie::queue(Cookie::forget($this->cookieName));
    }

    public function shouldIssueToken(): bool
    {
        return (bool) config('swift-auth.remember_me.enabled', true);
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    protected function isTokenExpired(RememberToken $token): bool
    {
        return $token->expires_at !== null
            && CarbonImmutable::now()->greaterThan(CarbonImmutable::parse($token->expires_at));
    }

    /**
     * @return array{0:null|string,1:null|string}
     */
    protected function splitCookie(string $cookieValue): array
    {
        $parts = explode(':', $cookieValue, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [null, null];
        }

        return [$parts[0], $parts[1]];
    }
}
