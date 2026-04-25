<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger for gift card balance mutations.
 *
 * Every `GiftCardService` write path (purchase, redeemAgainstBooking, void,
 * adjustment) writes one ledger row inside the same DB transaction as the
 * balance/status mutation. The `balance_after_cents` column caches the
 * running balance so BalanceHistoryRelationManager can render without
 * replaying the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_card_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('gift_card_id')->constrained()->cascadeOnDelete();
            // Matches GiftCardLedgerType enum: purchase | redemption | void | adjustment.
            $table->string('type');
            // Signed — positive for credits (purchase, adjustment up),
            // negative for debits (redemption, void).
            $table->integer('amount_cents');
            // Running balance snapshot after this entry; never negative.
            $table->unsignedInteger('balance_after_cents');
            $table->foreignUuid('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->text('reason')->nullable();
            // Append-only: no `updated_at`.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gift_card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_ledger_entries');
    }
};
