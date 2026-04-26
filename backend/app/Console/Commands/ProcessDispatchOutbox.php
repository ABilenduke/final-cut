<?php

namespace App\Console\Commands;

use App\Models\DispatchOutbox;
use App\Outbox\OutboxDispatcher;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Drains dispatch_outbox rows ready for delivery.
 *
 * Scheduled every minute by `routes/console.php` with
 * `withoutOverlapping(90)` and `runInBackground()` so a slow batch on
 * one tick can't stack up parallel workers.
 *
 * Per-row contract:
 *   - Up to BATCH_SIZE rows per invocation (`processed_at IS NULL AND
 *     available_at <= now() AND attempts < MAX_ATTEMPTS`).
 *   - Each successful dispatch writes `processed_at = now()` and
 *     `attempts = attempts + 1`.
 *   - Each thrown exception increments `attempts`, captures the message
 *     in `last_error`, and leaves `processed_at = null` so the row is
 *     retried on the next tick (until `attempts >= MAX_ATTEMPTS`).
 *   - At MAX_ATTEMPTS the row is parked with `failed_at = now()` and
 *     an error-level log fires for on-call.
 *   - An unknown `event_type` is a developer error, not a transient
 *     failure: the row is parked immediately with `failed_at = now()`.
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
        $batchSize = (int) $this->option('batch');

        $rows = DispatchOutbox::query()
            ->dispatchable()
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

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
                'attempts' => $row->attempts + 1,
                'last_error' => null,
            ]);
        } catch (InvalidArgumentException $e) {
            // Unknown event_type — park immediately. No amount of retrying
            // will conjure a job mapping into existence; surface it loudly.
            $row->update([
                'attempts' => $row->attempts + 1,
                'failed_at' => now(),
                'last_error' => $e->getMessage(),
            ]);

            logger()->error('outbox:dispatch unknown event_type, row parked', [
                'outbox_id' => $row->id,
                'event_type' => $row->event_type,
                'error' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            $attempts = $row->attempts + 1;
            $update = [
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
            ];

            if ($attempts >= DispatchOutbox::MAX_ATTEMPTS) {
                $update['failed_at'] = now();

                logger()->error('outbox:dispatch row reached MAX_ATTEMPTS, parked', [
                    'outbox_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);
            } else {
                logger()->warning('outbox:dispatch retryable failure', [
                    'outbox_id' => $row->id,
                    'event_type' => $row->event_type,
                    'attempts' => $attempts,
                    'error' => $e->getMessage(),
                ]);
            }

            $row->update($update);
        }
    }
}
