<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS booking_code_seq');

        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('confirmation_code')->unique();
            // RESTRICT, not CASCADE: a Booking is a financial record (Stripe
            // PaymentIntent id, confirmation code, totals). Deleting a Showtime
            // must never silently erase bookings — showtimes are soft-cancelled
            // via cancelled_at, and AuditoriumService guards the location/
            // auditorium cascade paths. (ultrareview P0 #5)
            $table->foreignUuid('showtime_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_email')->nullable();
            $table->string('status')->default('confirmed');
            $table->timestamp('flagged_at')->nullable();
            $table->string('flag_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total');
            $table->string('payment_method')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            // Client-supplied request key for booking-store idempotency. Nullable
            // (Postgres allows many NULLs under a unique index) so legacy/keyless
            // requests still insert; a retry that reuses the key replays the
            // original booking instead of double-charging. (ultrareview P0 #7)
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->timestamps();

            // Postgres does not auto-index FK columns. Both are hot read paths:
            // user_id (account orders/bookings/loyalty) and showtime_id (seat
            // availability + per-showtime booking lookups). (ultrareview perf)
            $table->index('user_id');
            $table->index('showtime_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        DB::statement('DROP SEQUENCE IF EXISTS booking_code_seq');
    }
};
