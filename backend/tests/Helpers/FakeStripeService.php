<?php

namespace Tests\Helpers;

use App\Services\StripeService;

class FakeStripeService extends StripeService
{
    private string $behavior = 'succeed';

    public function __construct()
    {
        // Skip parent constructor — no Stripe client needed
    }

    public array $createdPaymentIntents = [];

    public array $confirmedPaymentIntents = [];

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

        return \Stripe\PaymentIntent::constructFrom([
            'id'     => $paymentIntentId,
            'object' => 'payment_intent',
            'status' => 'succeeded',
        ]);
    }
}
