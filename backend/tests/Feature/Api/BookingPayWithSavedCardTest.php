<?php

use App\Models\User;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

test('an authenticated user can pay with a saved card — the intent carries their customer', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();
    $user = User::factory()->create(['stripe_customer_id' => 'cus_saved_77']);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_saved_card_1',
        'usingSavedCard' => true,
    ])->assertStatus(201);

    $intent = $stripe->createdPaymentIntents[0];
    expect($intent['customerId'] ?? null)->toBe('cus_saved_77')
        // Paying with an already-saved card must not re-request retention.
        ->and($intent['setupFutureUsage'] ?? null)->toBeNull();
});

test('a saved-card payment without a stripe customer is rejected before Stripe', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();
    $user = User::factory()->create(['stripe_customer_id' => null]);

    actingAs($user)->postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_saved_card_1',
        'usingSavedCard' => true,
    ])->assertStatus(400);

    expect($stripe->createdPaymentIntents)->toBeEmpty();
});

test('guests cannot use the saved-card path', function (): void {
    $fixture = $this->createShowtimeWithSeats();
    $stripe = $this->fakeStripe()->shouldSucceed();

    postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_saved_card_1',
        'email' => 'guest@example.com',
        'usingSavedCard' => true,
    ])->assertStatus(400);

    expect($stripe->createdPaymentIntents)->toBeEmpty();
});
