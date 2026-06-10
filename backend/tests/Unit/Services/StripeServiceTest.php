<?php

use App\Services\StripeService;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

function mockStripeClient(): array
{
    $piService = Mockery::mock(PaymentIntentService::class);

    $stripeClient = Mockery::mock(StripeClient::class)->makePartial();
    $stripeClient->paymentIntents = $piService;

    return [$stripeClient, $piService];
}

function fakePaymentIntent(string $status = 'succeeded'): PaymentIntent
{
    return PaymentIntent::constructFrom([
        'id' => 'pi_test_abc123',
        'object' => 'payment_intent',
        'amount' => 2500,
        'currency' => 'usd',
        'status' => $status,
    ]);
}

test('createPaymentIntent calls Stripe with correct parameters', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function (array $params) {
            return $params['amount'] === 2500
                && $params['currency'] === 'usd'
                && $params['payment_method'] === 'pm_test_xyz'
                && $params['confirm'] === true
                && $params['payment_method_types'] === ['card']
                && ! array_key_exists('automatic_payment_methods', $params);
        }), Mockery::type('array'))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(2500, 'pm_test_xyz');
});

test('createPaymentIntent forwards description and receipt_email when provided', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function (array $params) {
            return $params['description'] === 'Final Cut · Dune · 2026-05-13'
                && $params['receipt_email'] === 'fan@finalcut.test';
        }), Mockery::type('array'))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(
        2500,
        'pm_test_xyz',
        [],
        null,
        'Final Cut · Dune · 2026-05-13',
        'fan@finalcut.test',
    );
});

test('createPaymentIntent omits description and receipt_email when null', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function (array $params) {
            return ! array_key_exists('description', $params)
                && ! array_key_exists('receipt_email', $params);
        }), Mockery::type('array'))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(2500, 'pm_test_xyz');
});

test('createPaymentIntent forwards idempotency key via options', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::type('array'), Mockery::on(fn (array $opts) => $opts['idempotency_key'] === 'idem-xyz-123'))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(2500, 'pm_test_xyz', [], 'idem-xyz-123');
});

test('updatePaymentIntentMetadata patches metadata on an existing PaymentIntent', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('update')
        ->once()
        ->with('pi_test_abc123', Mockery::on(fn (array $params) => $params['metadata'] === ['confirmation_code' => 'CVF-A3X9K2']))
        ->andReturn(PaymentIntent::constructFrom([
            'id' => 'pi_test_abc123',
            'object' => 'payment_intent',
            'status' => 'succeeded',
            'metadata' => ['confirmation_code' => 'CVF-A3X9K2'],
        ]));

    $service = new StripeService(client: $client);
    $result = $service->updatePaymentIntentMetadata('pi_test_abc123', ['confirmation_code' => 'CVF-A3X9K2']);

    expect($result->metadata['confirmation_code'])->toBe('CVF-A3X9K2');
});

test('createPaymentIntent passes metadata through to Stripe', function () {
    [$client, $piService] = mockStripeClient();

    $metadata = ['booking_id' => '42', 'showtime_id' => '7'];

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $params) => $params['metadata'] === $metadata), Mockery::type('array'))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(2500, 'pm_test_xyz', $metadata);
});

test('createPaymentIntent returns PaymentIntent on success', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->andReturn(fakePaymentIntent('succeeded'));

    $service = new StripeService(client: $client);
    $result = $service->createPaymentIntent(2500, 'pm_test_xyz');

    expect($result)->toBeInstanceOf(PaymentIntent::class)
        ->and($result->status)->toBe('succeeded');
});

test('createPaymentIntent returns requires_action when 3DS is needed', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->andReturn(fakePaymentIntent('requires_action'));

    $service = new StripeService(client: $client);
    $result = $service->createPaymentIntent(2500, 'pm_test_xyz');

    expect($result->status)->toBe('requires_action');
});

test('createPaymentIntent propagates CardException for declined cards', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->andThrow(CardException::factory('Your card was declined.'));

    $service = new StripeService(client: $client);

    expect(fn () => $service->createPaymentIntent(2500, 'pm_declined'))
        ->toThrow(CardException::class);
});

test('createPaymentIntent propagates InvalidRequestException', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('create')
        ->once()
        ->andThrow(InvalidRequestException::factory('No such payment_method: pm_invalid'));

    $service = new StripeService(client: $client);

    expect(fn () => $service->createPaymentIntent(2500, 'pm_invalid'))
        ->toThrow(InvalidRequestException::class);
});

test('confirmPaymentIntent calls Stripe confirm with correct ID', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('confirm')
        ->once()
        ->with('pi_test_abc123')
        ->andReturn(fakePaymentIntent('succeeded'));

    $service = new StripeService(client: $client);
    $service->confirmPaymentIntent('pi_test_abc123');
});

test('confirmPaymentIntent returns PaymentIntent', function () {
    [$client, $piService] = mockStripeClient();

    $piService->shouldReceive('confirm')
        ->once()
        ->andReturn(fakePaymentIntent('succeeded'));

    $service = new StripeService(client: $client);
    $result = $service->confirmPaymentIntent('pi_test_abc123');

    expect($result)->toBeInstanceOf(PaymentIntent::class)
        ->and($result->status)->toBe('succeeded');
});

test('refundPaymentIntent calls Stripe refunds create with correct payment intent ID', function () {
    [$client, $piService] = mockStripeClient();

    $refundService = Mockery::mock(RefundService::class);
    $client->refunds = $refundService;

    $refundService->shouldReceive('create')
        ->once()
        ->with(
            // Full refund: no amount key; no idempotency key → empty options.
            Mockery::on(fn (array $params) => $params['payment_intent'] === 'pi_test_abc123'
                && ! array_key_exists('amount', $params)),
            [],
        )
        ->andReturn(Refund::constructFrom([
            'id' => 're_test_xyz',
            'object' => 'refund',
            'payment_intent' => 'pi_test_abc123',
            'status' => 'succeeded',
        ]));

    $service = new StripeService(client: $client);
    $result = $service->refundPaymentIntent('pi_test_abc123');

    expect($result)->toBeInstanceOf(Refund::class)
        ->and($result->status)->toBe('succeeded')
        ->and($result->payment_intent)->toBe('pi_test_abc123');
});

test('refundPaymentIntent forwards a partial amount and idempotency key when given', function () {
    [$client, $piService] = mockStripeClient();

    $refundService = Mockery::mock(RefundService::class);
    $client->refunds = $refundService;

    $refundService->shouldReceive('create')
        ->once()
        ->with(
            Mockery::on(fn (array $params) => $params['payment_intent'] === 'pi_test_abc123'
                && $params['amount'] === 1250),
            ['idempotency_key' => 'booking_refund:b-1'],
        )
        ->andReturn(Refund::constructFrom([
            'id' => 're_test_partial',
            'object' => 'refund',
            'payment_intent' => 'pi_test_abc123',
            'status' => 'succeeded',
        ]));

    $service = new StripeService(client: $client);
    $result = $service->refundPaymentIntent('pi_test_abc123', 1250, 'booking_refund:b-1');

    expect($result->id)->toBe('re_test_partial');
});

test('service reads stripe secret key from config when no key provided', function () {
    config(['services.stripe.secret' => 'sk_test_from_config']);

    $service = new StripeService;

    expect($service)->toBeInstanceOf(StripeService::class);
});
