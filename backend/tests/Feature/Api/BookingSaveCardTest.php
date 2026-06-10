<?php

use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

test('an authenticated checkout with saveCard attaches a customer and future usage to the intent', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();
    $user = User::factory()->create(['stripe_customer_id' => null]);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'saveCard' => true,
    ])->assertStatus(201);

    $intent = $stripe->createdPaymentIntents[0];
    expect($intent['customerId'] ?? null)->not->toBeNull()
        ->and($intent['setupFutureUsage'] ?? null)->toBe('on_session')
        ->and($user->refresh()->stripe_customer_id)->not->toBeNull();
});

test('without saveCard the intent carries no customer', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();
    $user = User::factory()->create(['stripe_customer_id' => null]);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
    ])->assertStatus(201);

    $intent = $stripe->createdPaymentIntents[0];
    expect($intent['customerId'] ?? null)->toBeNull()
        ->and($user->refresh()->stripe_customer_id)->toBeNull();
});

test('guests cannot save cards — the flag is ignored', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'guest@example.com',
        'saveCard' => true,
    ])->assertStatus(201);

    $intent = $stripe->createdPaymentIntents[0];
    expect($intent['customerId'] ?? null)->toBeNull();
});

test('a returning customer reuses their existing stripe customer id', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_existing_42']);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'saveCard' => true,
    ])->assertStatus(201);

    expect($stripe->createdCustomers)->toHaveCount(1)
        ->and($stripe->createdCustomers[0]['existingCustomerId'])->toBe('cus_existing_42')
        ->and($user->refresh()->stripe_customer_id)->toBe('cus_existing_42');
});
