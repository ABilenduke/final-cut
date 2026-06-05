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
            // Schema carries per_user_limit for a future v2 enforcement; v1
            // PromoCodeService::validateCode does not consult this column.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
