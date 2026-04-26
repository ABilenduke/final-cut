<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Generalised outbox row — a reliable handoff between an in-transaction write
 * and an asynchronous job. Callers create rows inside their domain
 * transaction; Plan 09's worker drains the table and dispatches the mapped
 * job per `event_type`.
 *
 * @property int $id
 * @property string $event_type
 * @property array $payload
 * @property Carbon $available_at
 * @property ?Carbon $processed_at
 * @property ?Carbon $failed_at
 * @property int $attempts
 * @property ?string $last_error
 */
// Worker columns (`processed_at`, `failed_at`, `attempts`, `last_error`) are
// fillable so Plan 09's outbox worker can update rows via `$row->update([...])`
// without tripping MassAssignmentException. Producers set `event_type`,
// `payload`, and optionally `available_at`; the worker owns the rest.
#[Fillable([
    'event_type',
    'payload',
    'available_at',
    'processed_at',
    'failed_at',
    'attempts',
    'last_error',
])]
class DispatchOutbox extends Model
{
    protected $table = 'dispatch_outbox';

    /** Maximum number of dispatch attempts before the worker parks a row. */
    public const MAX_ATTEMPTS = 5;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /** Rows ready for the worker to dispatch. */
    public function scopeDispatchable(Builder $query): Builder
    {
        return $query
            ->whereNull('processed_at')
            // Parked rows (`failed_at IS NOT NULL`) are excluded explicitly:
            // the unknown-event_type path sets `failed_at` immediately
            // (without raising attempts to MAX), and operators may set
            // `failed_at` manually to quarantine a row that needs human
            // investigation. Without this guard those rows keep cycling
            // through the worker and spamming logs.
            ->whereNull('failed_at')
            // Use Postgres `NOW()` directly instead of binding PHP's `now()`.
            // Laravel's Postgres grammar formats Carbon bindings with the
            // second-precision format `Y-m-d H:i:s`, truncating microseconds.
            // The column itself is `timestamp(6)` and `available_at` defaults
            // to `CURRENT_TIMESTAMP` (microsecond precision). A row inserted
            // mid-second (e.g. 12.93s) gets `available_at = 12.930…`, but a
            // PHP-bound `now()` becomes `12.000` and the comparison fails
            // until the next whole second ticks over. Postgres-side `NOW()`
            // shares the column's clock and precision, so the comparison is
            // monotonic regardless of how quickly the dispatcher runs after
            // the insert.
            ->whereRaw('available_at <= NOW()')
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }
}
