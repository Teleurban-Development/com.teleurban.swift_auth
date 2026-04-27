<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $prefix = (string) config('swift-auth.table_prefix', 'swift-auth_');
        $tenantKey = (string) config('swift-auth.multi_tenancy.tenant_key', 'id_tenant');
        $defaultTenant = (string) config('swift-auth.multi_tenancy.fallback_tenant_id', 'global');

        $usersTable = $prefix . 'Users';
        if (Schema::hasTable($usersTable) && !Schema::hasColumn($usersTable, $tenantKey)) {
            Schema::table($usersTable, function (Blueprint $table) use ($tenantKey, $defaultTenant): void {
                $table->string($tenantKey)->default($defaultTenant)->after('id_user');
                $table->index($tenantKey);
            });
        }

        $rolesTable = $prefix . 'Roles';
        if (Schema::hasTable($rolesTable) && !Schema::hasColumn($rolesTable, $tenantKey)) {
            Schema::table($rolesTable, function (Blueprint $table) use ($tenantKey, $defaultTenant): void {
                $table->string($tenantKey)->default($defaultTenant)->after('id_role');
                $table->index($tenantKey);
            });
        }
    }

    public function down(): void
    {
        $prefix = (string) config('swift-auth.table_prefix', 'swift-auth_');
        $tenantKey = (string) config('swift-auth.multi_tenancy.tenant_key', 'id_tenant');

        $usersTable = $prefix . 'Users';
        if (Schema::hasTable($usersTable) && Schema::hasColumn($usersTable, $tenantKey)) {
            Schema::table($usersTable, function (Blueprint $table) use ($tenantKey): void {
                $table->dropIndex([$tenantKey]);
                $table->dropColumn($tenantKey);
            });
        }

        $rolesTable = $prefix . 'Roles';
        if (Schema::hasTable($rolesTable) && Schema::hasColumn($rolesTable, $tenantKey)) {
            Schema::table($rolesTable, function (Blueprint $table) use ($tenantKey): void {
                $table->dropIndex([$tenantKey]);
                $table->dropColumn($tenantKey);
            });
        }
    }
};
