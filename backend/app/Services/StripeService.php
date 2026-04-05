<?php

namespace App\Services;

use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripeService
{
    private StripeClient $client;

    public function __construct(?string $apiKey = null, ?StripeClient $client = null)
    {
        $key = $apiKey ?? config('services.stripe.secret');

        $this->client = $client ?? new StripeClient($key ?: 'sk_not_configured');
    }

    /**
     * Creates and immediately confirms a PaymentIntent in one API call.
     *
     * Callers must check status === 'requires_action' to detect 3DS challenges.
     *
     * @throws \Stripe\Exception\CardException           Propagates — controller maps to 402
     * @throws \Stripe\Exception\InvalidRequestException Propagates — controller maps to 400
     */
    public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = []): PaymentIntent
    {
        return $this->client->paymentIntents->create([
            'amount'                    => $amount,
            'currency'                  => 'usd',
            'payment_method'            => $paymentMethodId,
            'confirm'                   => true,
            'automatic_payment_methods' => [
                'enabled'         => true,
                'allow_redirects' => 'never',
            ],
            'metadata' => $metadata,
        ]);
    }

    /**
     * Confirms an existing PaymentIntent after 3DS completion.
     *
     * @throws \Stripe\Exception\CardException           Propagates — controller maps to 402
     * @throws \Stripe\Exception\InvalidRequestException Propagates — controller maps to 400
     */
    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client->paymentIntents->confirm($paymentIntentId);
    }
}
