<?php

namespace App\Console\Commands;

use App\Models\DispatchOutbox;
use Illuminate\Console\Command;

/**
 * Removes successfully-processed dispatch_outbox rows older than the
 * retention horizon. Failed rows (failed_at IS NOT NULL) are kept
 * indefinitely so a human can investigate; the prune step never
 * silently disposes of a row that the worker couldn't deliver.
 *
 * Retention is 14 days, aligned with the activity_log retention
 * configured in `config/activitylog.php`. The two together let an
 * incident review correlate "what happened" (activity_log) with
 * "what was emitted" (outbox) over the same window.
 */
class PruneDispatchOutbox extends Command
{
    protected $signature = 'outbox:prune {--days=14 : Retention window in days}';

    protected $description = 'Delete successfully-processed outbox rows older than the retention window';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DispatchOutbox::query()
            ->whereNotNull('processed_at')
            ->whereNull('failed_at')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} processed outbox row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
