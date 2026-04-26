<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `admin_users` is a one-to-one admin profile/entitlement table keyed on
 * `users.id`. It does NOT carry identity (name/email/password live on
 * `users`); it carries admin-only metadata: last login attribution and a
 * disabled flag. Filament's `admin` guard authenticates `App\Models\User`
 * and `User::canAccessPanel()` requires an `AdminProfile` row that isn't
 * disabled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_users', function (Blueprint $table) {
            $table->foreignUuid('user_id')
                ->primary()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_users');
    }
};
