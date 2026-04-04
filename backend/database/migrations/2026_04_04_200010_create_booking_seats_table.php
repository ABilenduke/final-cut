<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_seats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('showtime_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('seat_id')->constrained()->cascadeOnDelete();
            $table->string('section')->nullable();
            $table->unsignedInteger('price');
            $table->timestamps();

            $table->index(['showtime_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_seats');
    }
};
