<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            // Uppercase-only by service contract; unique enforced at DB level.
            $table->string('code', 32)->unique();
            // 'percentage' (1-100) or 'fixed_cents' (positive integer cents).
            $table->string('discount_type');
            $table->unsignedInteger('amount');
            // null = unlimited total uses.
            $table->unsignedInteger('usage_limit')->nullable();
            // null = unlimited per customer. Enforced by PromoCodeService
            // (consume() under lock + a validateCode pre-check), counting prior
            // bookings via bookings.promo_code_id keyed on user_id or
            // lower(guest_email). (per-user-limit)
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            // Nullable-timestamp convention: NULL = active, set = when it was
            // deactivated (event metadata the old boolean threw away). The
            // PromoCode::is_active accessor derives the boolean for readers.
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->index(['deactivated_at', 'expires_at']);
        });

        // bookings.promo_code_id was created as a plain BIGINT column in the
        // create_bookings_table migration (which runs before this one). Wire the
        // FK now that promo_codes exists: nullOnDelete keeps the booking (a
        // financial record) when a promo is hard-deleted. (per-user-limit)
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreign('promo_code_id')->references('id')->on('promo_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the cross-table FK before the referenced table.
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropForeign(['promo_code_id']);
        });

        Schema::dropIfExists('promo_codes');
    }
};
