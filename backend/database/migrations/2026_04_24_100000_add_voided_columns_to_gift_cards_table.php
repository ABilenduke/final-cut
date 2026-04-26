<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive rather than in-place (CLAUDE.md "Pre-launch migrations" rule)
 * because `gift_cards` is created before `admin_users` in the migration
 * timeline (2026_04_04 < 2026_04_22), so an inline `foreignId` constraint
 * would fail at migrate time. This file lives AFTER the admin_users
 * migration so the FK is resolvable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('purchased_at');
            $table->text('voided_reason')->nullable()->after('voided_at');
            $table->foreignId('voided_by_admin_user_id')
                ->nullable()
                ->after('voided_reason')
                ->constrained('admin_users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by_admin_user_id');
            $table->dropColumn(['voided_at', 'voided_reason']);
        });
    }
};
