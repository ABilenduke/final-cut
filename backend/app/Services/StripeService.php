<?php

namespace App\Services;

use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct(?string $apiKey = null, ?StripeClient $client = null)
    {
        if ($client) {
            $this->client = $client;

            return;
        }

        $key = $apiKey ?? config('services.stripe.secret');

        if (empty($key)) {
            throw new \RuntimeException('Stripe API key is not configured. Set STRIPE_SECRET_KEY in your environment or services.stripe.secret in config.');
        }

        $this->client = new StripeClient($key);
    }

    /**
     * Creates and immediately confirms a PaymentIntent in one API call.
     *
     * Callers must check status === 'requires_action' to detect 3DS challenges.
     *
     * @throws CardException Propagates — controller maps to 402
     * @throws InvalidRequestException Propagates — controller maps to 400
     */
    public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = []): PaymentIntent
    {
        return $this->client->paymentIntents->create([
            'amount' => $amount,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
            'metadata' => $metadata,
        ]);
    }

    /**
     * Confirms an existing PaymentIntent after 3DS completion.
     *
     * @throws CardException Propagates — controller maps to 402
     * @throws InvalidRequestException Propagates — controller maps to 400
     */
    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client->paymentIntents->confirm($paymentIntentId);
    }

    /**
     * Issues a full refund for a captured PaymentIntent.
     *
     * Used as a compensating action when DB writes fail after payment has
     * already been captured, preventing orphaned charges.
     *
     * @throws ApiErrorException
     */
    public function refundPaymentIntent(string $paymentIntentId): Refund
    {
        return $this->client->refunds->create([
            'payment_intent' => $paymentIntentId,
        ]);
    }
}
