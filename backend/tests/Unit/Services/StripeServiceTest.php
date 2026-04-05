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
        'id'       => 'pi_test_abc123',
        'object'   => 'payment_intent',
        'amount'   => 2500,
        'currency' => 'usd',
        'status'   => $status,
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
                && $params['automatic_payment_methods']['enabled'] === true
                && $params['automatic_payment_methods']['allow_redirects'] === 'never';
        }))
        ->andReturn(fakePaymentIntent());

    $service = new StripeService(client: $client);
    $service->createPaymentIntent(2500, 'pm_test_xyz');
});

test('createPaymentIntent passes metadata through to Stripe', function () {
    [$client, $piService] = mockStripeClient();

    $metadata = ['booking_id' => '42', 'showtime_id' => '7'];

    $piService->shouldReceive('create')
        ->once()
        ->with(Mockery::on(fn (array $params) => $params['metadata'] === $metadata))
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
        ->with(Mockery::on(fn (array $params) => $params['payment_intent'] === 'pi_test_abc123'))
        ->andReturn(Refund::constructFrom([
            'id'             => 're_test_xyz',
            'object'         => 'refund',
            'payment_intent' => 'pi_test_abc123',
            'status'         => 'succeeded',
        ]));

    $service = new StripeService(client: $client);
    $result = $service->refundPaymentIntent('pi_test_abc123');

    expect($result)->toBeInstanceOf(Refund::class)
        ->and($result->status)->toBe('succeeded')
        ->and($result->payment_intent)->toBe('pi_test_abc123');
});

test('service reads stripe secret key from config when no key provided', function () {
    config(['services.stripe.secret' => 'sk_test_from_config']);

    $service = new StripeService();

    expect($service)->toBeInstanceOf(StripeService::class);
});
