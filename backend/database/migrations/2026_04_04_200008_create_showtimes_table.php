<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('showtimes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('movie_id');
            $table->foreignUuid('auditorium_id')->constrained('auditoriums')->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedInteger('price_standard');
            $table->unsignedInteger('price_premium');
            $table->unsignedInteger('price_accessible');
            $table->timestamps();

            $table->foreign('movie_id')->references('id')->on('movies')->cascadeOnDelete();
            $table->index(['movie_id', 'start_time']);
            $table->index('start_time');
            // Drives conflict-detection overlap queries on large schedules.
            // The EXCLUDE USING gist constraint (added in a separate migration) is the
            // authoritative guard against overlapping showtimes; this btree index is
            // for read-path query performance only.
            $table->index(['auditorium_id', 'start_time'], 'showtimes_aud_start_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('showtimes');
    }
};
