<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Large Adjustment Threshold
    |--------------------------------------------------------------------------
    |
    | Admin-initiated loyalty point adjustments with an absolute delta at or
    | above this value surface an elevated confirmation modal (Plan 07 spec
    | § 8). Dual-control approval is deferred to v2; this threshold + the
    | activity log are the compensating controls in v1.
    |
    */

    'large_adjustment_threshold' => env('LOYALTY_LARGE_ADJUSTMENT_THRESHOLD', 1000),
];
