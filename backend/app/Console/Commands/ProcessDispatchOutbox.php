<?php

namespace App\Console\Commands;

use App\Models\DispatchOutbox;
use App\Outbox\OutboxDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Drains dispatch_outbox rows ready for delivery.
 *
 * Scheduled every minute by `routes/console.php` with
 * `withoutOverlapping(2)` (minutes) and `runInBackground()`. Even with
 * the scheduler lock, ad-hoc manual runs and future multi-scheduler
 * deployments could pick the same rows concurrently — so each batch is
 * claimed inside a transaction with `lockForUpdate()` and Postgres'
 * `SKIP LOCKED` semantics so two workers can scan the queue at the same
 * time and never overlap. Claimed rows are reserved (a no-op `attempts`
 * bump) before the transaction commits, so the dispatcher path runs
 * outside the row lock without risking re-selection.
 *
 * Per-row contract:
 *   - Up to BATCH_SIZE rows per invocation (`processed_at IS NULL AND
 *     failed_at IS NULL AND available_at <= now() AND attempts <
 *     MAX_ATTEMPTS`).
 *   - Each successful dispatch writes `processed_at = now()` and
 *     `last_error = null` (attempts already incremented at claim time).
 *   - Each thrown exception leaves `processed_at = null`, captures
 *     the message in `last_error`, and the row is retried on the next
 *     tick (until `attempts >= MAX_ATTEMPTS`).
 *   - At MAX_ATTEMPTS the row is parked with `failed_at = now()` and
 *     an error-level log fires for on-call.
 *   - An unknown `event_type` (or a malformed payload missing required
 *     keys) is a developer error, not a transient failure: the row is
 *     parked immediately with `failed_at = now()`.
 *
 * Idempotency: each job's `handle()` is the source of idempotency
 * (e.g. NotifyCustomerOfShowtimeCancellation re-checks the booking's
 * flagged_at before sending mail). The outbox layer guarantees
 * at-least-once delivery; consumers guarantee no duplicate effect.
 */
class ProcessDispatchOutbox extends Command
{
    protected $signature = 'outbox:dispatch {--batch=100 : Maximum rows to process per invocation}';

    protected $description = 'Dispatch queued jobs for pending dispatch_outbox rows';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $batchOption = $this->option('batch');

        // Mirror PruneDispatchOutbox: a non-numeric or non-positive batch
        // would either be a no-op (cast to 0) or — if `limit()` ever stops
        // clamping — silently scan the whole queue. Refuse both and let the
        // scheduler / operator see the misconfig.
        if (! is_numeric($batchOption) || (int) $batchOption < 1) {
            $this->error("--batch must be a positive integer; got: {$batchOption}");

            return self::INVALID;
        }

        $batchSize = (int) $batchOption;

        // Claim a batch of rows atomically. `lock('FOR UPDATE SKIP LOCKED')`
        // is Postgres-specific (the project pins postgres) and ensures a
        // concurrent scheduler tick (or manual run) never picks rows we've
        // already locked — they're skipped over instead of blocking. The
        // claim bumps `attempts` so a crash between this transaction and
        // the dispatch loop below leaves the rows visibly "in flight" for
        // the next tick. Using `lock()` with a custom string instead of
        // `lockForUpdate()` so we can append SKIP LOCKED in one statement.
        $rows = DB::transaction(function () use ($batchSize) {
            $claimedRows = DispatchOutbox::query()
                ->dispatchable()
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            if ($claimedRows->isEmpty()) {
                return collect();
            }

            DispatchOutbox::query()
                ->whereIn('id', $claimedRows->pluck('id'))
                ->update(['attempts' => DB::raw('attempts + 1')]);

            // Reload so the in-memory rows reflect the bumped `attempts`
            // and so the dispatch loop has a fresh model snapshot.
            return DispatchOutbox::query()
                ->whereIn('id', $claimedRows->pluck('id'))
                ->get();
        });

        if ($rows->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Processing {$rows->count()} outbox row(s).");

        foreach ($rows as $row) {
            $this->dispatchRow($dispatcher, $row);
        }

        return self::SUCCESS;
    }

    private function dispatchRow(OutboxDispatcher $dispatcher, DispatchOutbox $row): void
    {
        try {
            $dispatcher->dispatch($row);

            $row->update([
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (InvalidArgumentException $e) {
            // Unknown event_type or malformed payload — park immediately.
            // No amount of retrying will conjure a job mapping (or a
            // missing payload key) into existence; surface it loudly.
            $row->update([
                'failed_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            logger()->error('outbox:dispatch developer error, row parked', [
                'outbox_id' => $row->id,
                'event_type' => $row->event_type,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $update = [
                'last_error' => $e->getMessage(),
            ];

            if ($row->attempts >= DispatchOutbox::MAX_ATTEMPTS) {
                $update['failed_at'] = now();

                logger()->error('outbox:dispatch row reached MAX_ATTEMPTS, parked', [
                    'outbox_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $row->attempts,
                    'error' => $e->getMessage(),
                ]);
            } else {
                logger()->warning('outbox:dispatch retryable failure', [
                    'outbox_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $row->attempts,
                    'error' => $e->getMessage(),
                ]);
            }

            $row->update($update);
        }
    }
}
