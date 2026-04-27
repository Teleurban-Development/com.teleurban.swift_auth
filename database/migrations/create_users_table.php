<?php

/**
 * Migration: create users table for SwiftAuth.
 *
 * PHP 8.1+
 *
 * @package Equidna\SwiftAuth\Database\Migrations
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prefix = (string) config('swift-auth.table_prefix', 'swift-auth_');

        Schema::create($prefix . 'Users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('id_tenant')->default('global');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('email_verification_token')->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_failed_login_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // Performance indexes
            $table->index('id_tenant');
            $table->index('name');
            $table->index('email_verification_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = (string) config('swift-auth.table_prefix', 'swift-auth_');
        Schema::dropIfExists($prefix . 'Users');
    }
};
