<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // btree_gist ships with PostgreSQL but must be enabled per-database.
        // IF NOT EXISTS so re-running migrations is a no-op.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        // `showtimes.start_time` / `end_time` are `timestamp` (without time zone)
        // per Laravel's dateTime() migration, so we use `tsrange` (immutable) rather
        // than `tstzrange` (requires timestamptz). Half-open range `[)` means
        // back-to-back showtimes (prev.end == next.start) correctly register as
        // NO conflict — matches `ShowtimeService::detectConflicts`'s UI rule.
        DB::statement(<<<'SQL'
            ALTER TABLE showtimes
              ADD CONSTRAINT showtimes_no_overlap EXCLUDE USING gist (
                auditorium_id WITH =,
                tsrange(start_time, end_time, '[)') WITH &&
              ) WHERE (cancelled_at IS NULL)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE showtimes DROP CONSTRAINT IF EXISTS showtimes_no_overlap');
        // btree_gist extension is left in place — dropping a shared extension is not safe.
    }
};
