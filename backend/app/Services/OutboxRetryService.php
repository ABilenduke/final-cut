<?php

namespace App\Services;

use App\Exceptions\OutboxRetryException;
use App\Models\DispatchOutbox;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Operator un-park for dispatch_outbox rows (admin-v2 Plan 08). Resetting
 * the row makes it visible to the unchanged `dispatchable()` scope, so the
 * next `outbox:dispatch` tick re-attempts delivery with a full retry budget.
 */
class OutboxRetryService
{
    use LogsAdminActivity;

    public const EVENT_RETRIED = 'outbox.retried';

    /**
     * @throws OutboxRetryException When the row is not parked.
     */
    public function retry(DispatchOutbox $row, User $actor): void
    {
        DB::transaction(function () use ($row, $actor): void {
            /** @var DispatchOutbox $locked */
            $locked = DispatchOutbox::whereKey($row->id)->lockForUpdate()->firstOrFail();

            if ($locked->failed_at === null) {
                throw new OutboxRetryException(OutboxRetryException::REASON_NOT_PARKED);
            }

            $previousError = $locked->last_error;

            $locked->update([
                'attempts' => 0,
                'failed_at' => null,
                'last_error' => null,
                'available_at' => now(),
            ]);

            $this->logIfAdmin(self::EVENT_RETRIED, $locked, $actor, [
                'event_type' => $locked->event_type,
                'previous_error' => $previousError,
            ]);
        });
    }
}
