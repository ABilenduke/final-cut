<?php

use App\Models\PromoCode;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\postJson;

uses(BookingTestHelper::class);

beforeEach(function (): void {
    $this->fakeStripe();
});

/*
|--------------------------------------------------------------------------
| HTTP-level coverage for the DB-backed promo code path after Plan 08
| retrofitted BookingController off `config/promo_codes.php`.
|--------------------------------------------------------------------------
*/

test('valid percentage promo code applies discount', function (): void {
    PromoCode::factory()->percentage(10)->create(['code' => 'TENPCT']);
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'TENPCT',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.discount'))->toBe(120); // 10% of 1200
});

test('lowercase input is normalised to uppercase before lookup', function (): void {
    PromoCode::factory()->fixed(250)->create(['code' => 'SPRING25']);
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'spring25',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.discount'))->toBe(250);
});

test('expired promo code returns 400', function (): void {
    PromoCode::factory()->expired()->create(['code' => 'OLDYEAR']);
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'OLDYEAR',
    ]);

    $response->assertStatus(400);
    expect($response->json('errors.0.field'))->toBe('promoCode');
});

test('inactive promo code returns 400', function (): void {
    PromoCode::factory()->inactive()->create(['code' => 'OFFLINE']);
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'OFFLINE',
    ]);

    $response->assertStatus(400);
});

test('over-limit promo code returns 400', function (): void {
    PromoCode::factory()->withUsage(5, 5)->create(['code' => 'MAXED']);
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'MAXED',
    ]);

    $response->assertStatus(400);
});

test('non-existent promo code returns 400', function (): void {
    $fixture = $this->createShowtimeWithSeats();

    $response = postJson($this->bookingUrl($fixture['location']), [
        'showtimeId' => $fixture['showtime']->id,
        'seatIds' => [$fixture['seats'][0]->id],
        'paymentMethodId' => 'pm_test_visa',
        'email' => 'g@example.com',
        'promoCode' => 'GHOSTCODE',
    ]);

    $response->assertStatus(400);
});
