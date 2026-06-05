<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from the booking finalize phase when a gift card's balance dropped
 * below the amount the PaymentIntent was sized against (a concurrent
 * redemption during the Stripe call / 3DS window). The caller issues a
 * compensating refund and returns a 409 so the customer can restart with
 * current pricing rather than being silently under-charged.
 */
class GiftCardBalanceChangedException extends RuntimeException {}
