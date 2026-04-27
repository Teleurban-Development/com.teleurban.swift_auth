<?php

namespace Equidna\SwiftAuth\Classes\Auth\Services;

use Illuminate\Http\Request;

class TokenMetadataValidator
{
    public function validate(
        string $tokenIp,
        string $tokenUserAgent,
        ?string $tokenDeviceName,
        Request $request
    ): bool {
        $mismatches = $this->buildMismatches($tokenIp, $tokenUserAgent, $tokenDeviceName, $request);

        return empty($mismatches);
    }

    /**
     * @param array{ip: string, user_agent: string, device_name: ?string, user_id: int|string|null} $tokenData
     */
    public function validateWithLogging(
        array $tokenData, // ['ip' => ..., 'user_agent' => ..., 'device_name' => ..., 'user_id' => ...]
        Request $request
    ): bool {
        $tokenIp        = $tokenData['ip'];
        $tokenUserAgent = $tokenData['user_agent'];
        $tokenDeviceName = $tokenData['device_name'] ?? null;

        $mismatches = $this->buildMismatches($tokenIp, $tokenUserAgent, $tokenDeviceName, $request);

        if (empty($mismatches)) {
            return true;
        }

        logger()->warning('swift-auth.remember_me.mismatch', [
            'mismatched_fields' => $mismatches,
            'token' => [
                'user_id' => $tokenData['user_id'] ?? null,
                'ip' => $tokenIp,
            ],
            'request' => [
                'ip' => $request->ip(),
            ],
        ]);

        return false;
    }

    /**
     * Compares token metadata against the current request and returns the fields that do not match.
     *
     * @return string[]
     */
    private function buildMismatches(
        string $tokenIp,
        string $tokenUserAgent,
        ?string $tokenDeviceName,
        Request $request
    ): array {
        $policy     = config('swift-auth.remember_me.policy', 'strict');
        $mismatches = [];

        if (!$this->checkIp($policy, $tokenIp, $request->ip())) {
            $mismatches[] = 'ip';
        }

        if ($tokenUserAgent !== $request->userAgent()) {
            $mismatches[] = 'user_agent';
        }

        $requireDevice = config('swift-auth.remember_me.require_device_header', false);
        $deviceHeader  = config('swift-auth.remember_me.device_header', 'X-Device-Id');

        if ($requireDevice || $tokenDeviceName !== null) {
            $requestDevice = $request->header($deviceHeader);
            if ($tokenDeviceName !== $requestDevice) {
                $mismatches[] = 'device';
            }
        }

        return $mismatches;
    }

    protected function checkIp(string $policy, string $tokenIp, ?string $requestIp): bool
    {
        if ($tokenIp === $requestIp) {
            return true;
        }

        if ($policy === 'lenient' && config('swift-auth.remember_me.allow_same_subnet', true)) {
            $subnet = config('swift-auth.remember_me.subnet_mask', 24);
            return $this->cidrMatch($requestIp, $tokenIp, $subnet);
        }

        return false;
    }

    protected function cidrMatch(?string $ip, string $target, int $mask): bool
    {
        if (!$ip || !$target) {
            return false;
        }

        $ipLong     = ip2long($ip);
        $targetLong = ip2long($target);

        if ($ipLong === false || $targetLong === false) {
            return false;
        }

        $maskLong = -1 << (32 - $mask);

        return ($ipLong & $maskLong) === ($targetLong & $maskLong);
    }
}
