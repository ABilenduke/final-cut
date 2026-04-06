<?php

namespace Tests\Helpers;

use App\Services\StripeService;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\SetupIntent;

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

    public array $createdCustomers = [];

    public array $createdSetupIntents = [];

    public array $retrievedPaymentMethods = [];

    public array $detachedPaymentMethods = [];

    private array $fakePaymentMethods = [];

    private string $retrievedPmCustomerId = 'cus_fake_xxx';

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

    public function createPaymentIntent(int $amount, string $paymentMethodId, array $metadata = []): PaymentIntent
    {
        $this->createCallCount++;
        $this->createdPaymentIntents[] = [
            'amount' => $amount,
            'paymentMethodId' => $paymentMethodId,
            'metadata' => $metadata,
        ];

        if ($this->behavior === 'decline') {
            throw CardException::factory(
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
            throw InvalidRequestException::factory(
                $this->failureMessage,
                400,
            );
        }

        if ($this->behavior === 'api_error') {
            throw ApiConnectionException::factory(
                $this->failureMessage,
                503,
            );
        }

        if ($this->behavior === 'non_terminal') {
            return PaymentIntent::constructFrom([
                'id' => 'pi_fake_nonterminal_xxx',
                'object' => 'payment_intent',
                'status' => $this->failureMessage,
                'client_secret' => null,
            ]);
        }

        if ($this->behavior === 'require3ds') {
            return PaymentIntent::constructFrom([
                'id' => 'pi_fake_3ds_xxx',
                'object' => 'payment_intent',
                'status' => 'requires_action',
                'client_secret' => 'pi_fake_3ds_xxx_secret_xxx',
            ]);
        }

        return PaymentIntent::constructFrom([
            'id' => 'pi_fake_xxx',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'client_secret' => null,
        ]);
    }

    public function confirmPaymentIntent(string $paymentIntentId): PaymentIntent
    {
        $this->confirmedPaymentIntents[] = ['paymentIntentId' => $paymentIntentId];

        if ($this->behavior === 'invalid_request') {
            throw InvalidRequestException::factory(
                $this->failureMessage,
                400,
            );
        }

        if ($this->behavior === 'api_error') {
            throw ApiConnectionException::factory(
                $this->failureMessage,
                503,
            );
        }

        return PaymentIntent::constructFrom([
            'id' => $paymentIntentId,
            'object' => 'payment_intent',
            'status' => 'succeeded',
        ]);
    }

    public function refundPaymentIntent(string $paymentIntentId): Refund
    {
        $this->refundedPaymentIntents[] = ['paymentIntentId' => $paymentIntentId];

        return Refund::constructFrom([
            'id' => 're_fake_xxx',
            'object' => 'refund',
            'payment_intent' => $paymentIntentId,
            'status' => 'succeeded',
        ]);
    }

    public function withPaymentMethods(array $methods): static
    {
        $this->fakePaymentMethods = $methods;

        return $this;
    }

    public function withRetrievedPmCustomer(string $customerId): static
    {
        $this->retrievedPmCustomerId = $customerId;

        return $this;
    }

    public function getOrCreateCustomer(string $email, ?string $existingCustomerId = null): Customer
    {
        $this->throwIfConfiguredToFail();

        $this->createdCustomers[] = ['email' => $email, 'existingCustomerId' => $existingCustomerId];
        $id = $existingCustomerId ?? 'cus_fake_'.substr(md5($email), 0, 8);

        return Customer::constructFrom([
            'id' => $id,
            'object' => 'customer',
            'email' => $email,
        ]);
    }

    public function listPaymentMethods(string $customerId): array
    {
        $this->throwIfConfiguredToFail();

        if (empty($this->fakePaymentMethods)) {
            return [];
        }

        return array_map(fn ($pm) => PaymentMethod::constructFrom($pm), $this->fakePaymentMethods);
    }

    public function createSetupIntent(string $customerId): SetupIntent
    {
        $this->throwIfConfiguredToFail();

        $this->createdSetupIntents[] = ['customerId' => $customerId];

        return SetupIntent::constructFrom([
            'id' => 'seti_fake_xxx',
            'object' => 'setup_intent',
            'client_secret' => 'seti_fake_xxx_secret_xxx',
            'customer' => $customerId,
        ]);
    }

    public function retrievePaymentMethod(string $paymentMethodId): PaymentMethod
    {
        $this->throwIfConfiguredToFail();

        $this->retrievedPaymentMethods[] = ['paymentMethodId' => $paymentMethodId];

        return PaymentMethod::constructFrom([
            'id' => $paymentMethodId,
            'object' => 'payment_method',
            'customer' => $this->retrievedPmCustomerId,
            'card' => [
                'brand' => 'visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
            ],
        ]);
    }

    public function detachPaymentMethod(string $paymentMethodId): PaymentMethod
    {
        $this->throwIfConfiguredToFail();

        $this->detachedPaymentMethods[] = ['paymentMethodId' => $paymentMethodId];

        return PaymentMethod::constructFrom([
            'id' => $paymentMethodId,
            'object' => 'payment_method',
        ]);
    }

    /**
     * Throws the configured exception if the behavior is set to a failure mode.
     * Used by customer/PM methods to respect shouldFailWithInvalidRequest and shouldFailWithApiError.
     */
    private function throwIfConfiguredToFail(): void
    {
        if ($this->behavior === 'invalid_request') {
            throw InvalidRequestException::factory(
                $this->failureMessage,
                400,
            );
        }

        if ($this->behavior === 'api_error') {
            throw ApiConnectionException::factory(
                $this->failureMessage,
                503,
            );
        }
    }
}
