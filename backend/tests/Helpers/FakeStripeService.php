<?php

namespace Tests\Helpers;

use App\Services\StripeService;

class FakeStripeService extends StripeService
{
    private string $behavior = 'succeed';

    private string $failureMessage = '';

    public function __construct()
    {
        // Skip parent constructor — no Stripe client needed
    }

    public array $createdPaymentIntents = [];

    public array $confirmedPaymentIntents = [];

    public array $refundedPaymentIntents = [];

    public int $createCallCount = 0;

    public function shouldSucceed(): static
    {
        $this->behavior = 'succeed';

        return $this;
    }

    public function shouldRequire3ds(): static
    {
        $this->behavior = 'require3ds';

        return $this;
    }

    public function shouldDecline(): static
    {
        $this->behavior = 'decline';

        return $this;
    }

    public function shouldFailWithInvalidRequest(string $message = 'No such payment method: pm_expired'): static
    {
        $this->behavior = 'invalid_request';
        $this->failureMessage = $message;

        return $this;
    }

    public function shouldFailWithApiError(string $message = 'Stripe service unavailable'): static
    {
        $this->behavior = 'api_error';
        $this->failureMessage = $message;

        return $this;
    }

    public function shouldReturnNonTerminalStatus(string $status = 'processing'): static
    {
        $this->behavior = 'non_terminal';
        $this->failureMessage = $status;

        return $this;
    }

    public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = []): \Stripe\PaymentIntent
    {
        $this->createCallCount++;
        $this->createdPaymentIntents[] = [
            'amount'          => $amount,
            'paymentMethodId' => $paymentMethodId,
            'metadata'        => $metadata,
        ];

        if ($this->behavior === 'decline') {
            throw \Stripe\Exception\CardException::factory(
                'Your card was declined.',
                402,
                null,
                null,
                null,
                'card_declined',
                'card_declined',
                null,
            );
        }

        if ($this->behavior === 'invalid_request') {
            throw \Stripe\Exception\InvalidRequestException::factory(
                $this->failureMessage,
                400,
            );
        }

        if ($this->behavior === 'api_error') {
            throw \Stripe\Exception\ApiConnectionException::factory(
                $this->failureMessage,
                503,
            );
        }

        if ($this->behavior === 'non_terminal') {
            return \Stripe\PaymentIntent::constructFrom([
                'id'            => 'pi_fake_nonterminal_xxx',
                'object'        => 'payment_intent',
                'status'        => $this->failureMessage,
                'client_secret' => null,
            ]);
        }

        if ($this->behavior === 'require3ds') {
            return \Stripe\PaymentIntent::constructFrom([
                'id'            => 'pi_fake_3ds_xxx',
                'object'        => 'payment_intent',
                'status'        => 'requires_action',
                'client_secret' => 'pi_fake_3ds_xxx_secret_xxx',
            ]);
        }

        return \Stripe\PaymentIntent::constructFrom([
            'id'            => 'pi_fake_xxx',
            'object'        => 'payment_intent',
            'status'        => 'succeeded',
            'client_secret' => null,
        ]);
    }

    public function confirmPaymentIntent(string $paymentIntentId): \Stripe\PaymentIntent
    {
        $this->confirmedPaymentIntents[] = ['paymentIntentId' => $paymentIntentId];

        if ($this->behavior === 'invalid_request') {
            throw \Stripe\Exception\InvalidRequestException::factory(
                $this->failureMessage,
                400,
            );
        }

        if ($this->behavior === 'api_error') {
            throw \Stripe\Exception\ApiConnectionException::factory(
                $this->failureMessage,
                503,
            );
        }

        return \Stripe\PaymentIntent::constructFrom([
            'id'     => $paymentIntentId,
            'object' => 'payment_intent',
            'status' => 'succeeded',
        ]);
    }

    public function refundPaymentIntent(string $paymentIntentId): \Stripe\Refund
    {
        $this->refundedPaymentIntents[] = ['paymentIntentId' => $paymentIntentId];

        return \Stripe\Refund::constructFrom([
            'id'                => 're_fake_xxx',
            'object'            => 'refund',
            'payment_intent'    => $paymentIntentId,
            'status'            => 'succeeded',
        ]);
    }
}
