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
            ->where('available_at', '<=', now())
            ->where('attempts', '<', self::MAX_ATTEMPTS);
    }
}
