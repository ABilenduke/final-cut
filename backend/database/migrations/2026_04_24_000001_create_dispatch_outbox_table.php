<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generalised outbox pattern.
 *
 * Callers insert rows inside their own transaction so the event intent commits
 * atomically with domain state. A scheduled worker (wired in Plan 09) drains
 * pending rows and dispatches the mapped job per `event_type`. This closes the
 * TOCTOU gap between `DB::afterCommit` and `dispatch()` — a Redis outage at
 * the moment of commit would otherwise silently drop the job.
 *
 * Not showtime-specific: future events (gift-card voids, menu availability,
 * loyalty adjustments) reuse this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->jsonb('payload');
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Worker index: find unprocessed rows ready to dispatch, ordered by creation.
            $table->index(['processed_at', 'available_at', 'event_type'], 'dispatch_outbox_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_outbox');
    }
};
