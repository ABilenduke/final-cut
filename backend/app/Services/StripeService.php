<?php

namespace App\Services;

use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Refund;
use Stripe\SetupIntent;
use Stripe\StripeClient;

class StripeService
{
    private ?StripeClient $client;

    private ?string $apiKey;

    public function __construct(?string $apiKey = null, ?StripeClient $client = null)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    private function client(): StripeClient
    {
        if ($this->client) {
            return $this->client;
        }

        $key = $this->apiKey ?? config('services.stripe.secret');

        if (empty($key)) {
            throw new \RuntimeException('Stripe API key is not configured. Set STRIPE_SECRET_KEY in your environment or services.stripe.secret in config.');
        }

        return $this->client = new StripeClient($key);
    }

    /**
     * Creates and immediately confirms a PaymentIntent in one API call.
     *
     * Callers must check status === 'requires_action' to detect 3DS challenges.
     *
     * @param  ?string  $idempotencyKey  Optional Stripe idempotency key for request deduplication.
     *
     * @throws CardException Propagates — controller maps to 402
     * @throws InvalidRequestException Propagates — controller maps to 400
     */
    public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = [], ?string $idempotencyKey = null): PaymentIntent
    {
        $options = [];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        return $this->client()->paymentIntents->create([
            'amount' => $amount,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
            'metadata' => $metadata,
        ], $options);
    }

    /**
     * Confirms an existing PaymentIntent after 3DS completion.
     *
     * @throws CardException Propagates — controller maps to 402
     * @throws InvalidRequestException Propagates — controller maps to 400
     */
    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->client()->paymentIntents->confirm($paymentIntentId);
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
        return $this->client()->refunds->create([
            'payment_intent' => $paymentIntentId,
        ]);
    }

    /**
     * Retrieves an existing Stripe Customer or creates a new one.
     */
    public function getOrCreateCustomer(string $email, ?string $existingCustomerId = null): Customer
    {
        if ($existingCustomerId) {
            return $this->client()->customers->retrieve($existingCustomerId);
        }

        return $this->client()->customers->create(['email' => $email]);
    }

    /**
     * Lists all card payment methods for a Stripe Customer.
     *
     * @return StripePaymentMethod[]
     */
    public function listPaymentMethods(string $customerId): array
    {
        return $this->client()->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
        ])->data;
    }

    /**
     * Creates a SetupIntent for saving a card to a Stripe Customer.
     */
    public function createSetupIntent(string $customerId): SetupIntent
    {
        return $this->client()->setupIntents->create(['customer' => $customerId]);
    }

    /**
     * Retrieves a payment method by ID.
     */
    public function retrievePaymentMethod(string $paymentMethodId): StripePaymentMethod
    {
        return $this->client()->paymentMethods->retrieve($paymentMethodId);
    }

    /**
     * Detaches a payment method from its customer.
     */
    public function detachPaymentMethod(string $paymentMethodId): StripePaymentMethod
    {
        return $this->client()->paymentMethods->detach($paymentMethodId);
    }
}
