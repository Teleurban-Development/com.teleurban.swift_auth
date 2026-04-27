<?php

/**
 * Migration: create roles and pivot tables for SwiftAuth.
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

        Schema::create($prefix . 'Roles', function (Blueprint $table) {
            $table->id('id_role');
            $table->string('id_tenant')->default('global');
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->json('actions'); // Changed from string to JSON for better data structure
            $table->timestamps();

            // Performance indexes
            $table->index('id_tenant');
            $table->index('name');
        });
        Schema::create($prefix . 'UsersRoles', function (Blueprint $table) use ($prefix) {
            $table->unsignedBigInteger('id_user');
            $table->unsignedBigInteger('id_role');
            $table->primary(['id_user', 'id_role']);
            $table->foreign('id_user')->references('id_user')->on($prefix . 'Users')->onDelete('cascade');
            $table->foreign('id_role')->references('id_role')->on($prefix . 'Roles')->onDelete('cascade');

            // Performance indexes on foreign keys
            $table->index('id_user');
            $table->index('id_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = (string) config('swift-auth.table_prefix', 'swift-auth_');
        Schema::dropIfExists($prefix . 'UsersRoles');
        Schema::dropIfExists($prefix . 'Roles');
    }
};
