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
            // Microsecond precision is required: Laravel's default `timestamp(...)`
            // creates `timestamp(0)` on Postgres, which ROUNDS the stored value
            // to the nearest whole second. With second-precision and a default
            // of CURRENT_TIMESTAMP, a row inserted at PHP wall-clock 12.6s gets
            // `available_at = 13.0s`. The dispatchable scope then evaluates
            // `available_at <= now()` against PHP's `now()` (still ~12.7s) and
            // excludes the freshly-inserted row — the dispatcher silently skips
            // the row until the next minute's tick. Sub-second precision keeps
            // both sides of that comparison on the same time axis.
            $table->timestamp('available_at', 6)->useCurrent();
            $table->timestamp('processed_at', 6)->nullable();
            $table->timestamp('failed_at', 6)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps(6);

            // Worker index: find unprocessed rows ready to dispatch, ordered by creation.
            $table->index(['processed_at', 'available_at', 'event_type'], 'dispatch_outbox_pending_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_outbox');
    }
};
