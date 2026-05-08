<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the three design/delivery fields the redesigned customer gift-card
 * surface introduces. Additive (not in-place) because an additive
 * `voided_columns` migration already shipped against gift_cards — pre-launch
 * parity is unclear, so we default to additive per CLAUDE.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->string('edition')->default('reactor')->after('message');
            $table->string('delivery_method')->default('email')->after('edition');
            $table->timestamp('scheduled_send_at')->nullable()->after('delivery_method');
        });
    }

    public function down(): void
    {
        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropColumn(['edition', 'delivery_method', 'scheduled_send_at']);
        });
    }
};
