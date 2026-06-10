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
     * @param  ?string  $description  Human-readable charge description shown in the Stripe dashboard.
     * @param  ?string  $receiptEmail  Address to send Stripe's hosted receipt to.
     *
     * @throws CardException Propagates — controller maps to 402
     * @throws InvalidRequestException Propagates — controller maps to 400
     */
    public function createPaymentIntent(
        int $amount,
        string $paymentMethodId,
        array $metadata = [],
        ?string $idempotencyKey = null,
        ?string $description = null,
        ?string $receiptEmail = null,
    ): PaymentIntent {
        $options = [];

        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        $params = [
            'amount' => $amount,
            'currency' => 'usd',
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            // Card-only flow. `payment_method_types: ['card']` is more explicit
            // than `automatic_payment_methods` for our use case and avoids any
            // ambiguity around redirect-based methods we don't support yet.
            'payment_method_types' => ['card'],
            'metadata' => $metadata,
        ];

        if ($description !== null && $description !== '') {
            $params['description'] = $description;
        }

        if ($receiptEmail !== null && $receiptEmail !== '') {
            $params['receipt_email'] = $receiptEmail;
        }

        return $this->client()->paymentIntents->create($params, $options);
    }

    /**
     * Best-effort metadata patch for an existing PaymentIntent — used to attach
     * a booking's confirmation_code after the row has been finalized. Stripe
     * accepts the merge semantics natively, so we only send the new keys.
     *
     * Callers should not let a failure here surface as a user error: the
     * payment has already succeeded by the time we get here. Log and move on.
     *
     * @throws ApiErrorException
     */
    public function updatePaymentIntentMetadata(string $paymentIntentId, array $metadata): PaymentIntent
    {
        return $this->client()->paymentIntents->update($paymentIntentId, [
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
        return $this->client()->paymentIntents->confirm($paymentIntentId);
    }

    /**
     * Issues a refund for a captured PaymentIntent — full by default, partial
     * when `$amount` (cents) is given.
     *
     * Used as a compensating action when DB writes fail after payment has
     * already been captured, and by the admin refund flow
     * (`BookingRefundService`).
     *
     * @param  ?string  $idempotencyKey  Deterministic Stripe idempotency key.
     *                                   Pass one whenever a retry is possible:
     *                                   on an ambiguous network failure Stripe
     *                                   may have created the refund even though
     *                                   the response was lost, and the key makes
     *                                   the retry replay it instead of erroring
     *                                   on an already-refunded charge.
     *
     * @throws ApiErrorException
     */
    public function refundPaymentIntent(
        string $paymentIntentId,
        ?int $amount = null,
        ?string $idempotencyKey = null,
    ): Refund {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        $options = [];
        if ($idempotencyKey !== null) {
            $options['idempotency_key'] = $idempotencyKey;
        }

        return $this->client()->refunds->create($params, $options);
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
