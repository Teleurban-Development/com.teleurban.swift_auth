<?php

declare(strict_types=1);

namespace Equidna\SwiftAuth\Support\Tenancy;

use Equidna\BeeHive\Contracts\TenantResolverInterface;
use Illuminate\Http\Request;

class SwiftAuthTenantResolver implements TenantResolverInterface
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function resolveTenantId(): ?string
    {
        $enabled = (bool) config('swift-auth.multi_tenancy.enabled', false);

        if (!$enabled) {
            return $this->normalize(config('swift-auth.multi_tenancy.fallback_tenant_id', 'global'));
        }

        $headerKey = (string) config('swift-auth.multi_tenancy.request_sources.header', 'X-Tenant-Id');
        $queryKey = (string) config('swift-auth.multi_tenancy.request_sources.query', 'tenant_id');
        $sessionKey = (string) config('swift-auth.multi_tenancy.session_key', 'swift_auth_tenant_id');

        $fromHeader = $this->normalize($this->request->header($headerKey));
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        $fromQuery = $this->normalize($this->request->query($queryKey));
        if ($fromQuery !== null) {
            return $fromQuery;
        }

        if ($this->request->hasSession()) {
            $fromSession = $this->normalize($this->request->session()->get($sessionKey));
            if ($fromSession !== null) {
                return $fromSession;
            }
        }

        $user = $this->request->user();
        if (is_object($user) && isset($user->id_tenant)) {
            return $this->normalize($user->id_tenant);
        }

        return $this->normalize(config('swift-auth.multi_tenancy.fallback_tenant_id', null));
    }

    private function normalize(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $tenantId = trim((string) $value);

        return $tenantId === '' ? null : $tenantId;
    }
}
