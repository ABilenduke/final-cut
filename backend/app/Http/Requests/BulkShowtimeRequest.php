<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Thin data object carrying an already-validated, conflict-filtered batch of
 * showtimes into `ShowtimeService::bulkCreate`. Validation happens upstream in
 * the Filament `BulkCreateShowtimes` page; the service trusts the input
 * shape and enforces only domain invariants (movie runtime, DB constraint).
 *
 * Not a FormRequest because this never comes directly off an HTTP payload —
 * the bulk-create page constructs one after pre-check and user confirmation.
 */
final class BulkShowtimeRequest
{
    /**
     * @param  Collection<int, array{start_time: CarbonImmutable, end_time: CarbonImmutable}>  $tuples
     *                                                                                                  Each tuple is the creatable subset — conflicts were already filtered out
     *                                                                                                  and end_time pre-computed from movie runtime + auditorium cleanup.
     */
    public function __construct(
        public readonly string $movieId,
        public readonly string $auditoriumId,
        public readonly Collection $tuples,
        public readonly int $priceStandard,
        public readonly int $pricePremium,
        public readonly int $priceAccessible,
    ) {}
}
