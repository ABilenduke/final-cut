<?php

use App\Enums\InquiryStatus;
use App\Enums\RentalEventType;
use App\Models\RentalInquiry;
use Illuminate\Routing\Middleware\ThrottleRequests;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function validRentalPayload(array $overrides = []): array
{
    return array_merge([
        'eventType' => 'birthday',
        'preferredDate' => now()->addWeek()->toDateString(),
        'guestCount' => 50,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '555-123-4567',
        'message' => 'We would like to book for a birthday party.',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

test('creates rental inquiry successfully', function () {
    postJson('/api/rentals/inquiry', validRentalPayload())
        ->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['success', 'inquiryId'],
        ])
        ->assertJsonPath('data.success', true);

    $inquiry = RentalInquiry::first();

    expect($inquiry)->not->toBeNull()
        ->and($inquiry->status)->toBe(InquiryStatus::Pending)
        ->and($inquiry->event_type)->toBe(RentalEventType::Birthday)
        ->and($inquiry->name)->toBe('Jane Doe')
        ->and($inquiry->email)->toBe('jane@example.com');
});

test('phone is optional', function () {
    postJson('/api/rentals/inquiry', validRentalPayload(['phone' => null]))
        ->assertStatus(201);

    expect(RentalInquiry::first()->phone)->toBeNull();
});

test('message is optional', function () {
    postJson('/api/rentals/inquiry', validRentalPayload(['message' => null]))
        ->assertStatus(201);

    expect(RentalInquiry::first()->message)->toBeNull();
});

test('all valid event types accepted', function () {
    foreach (['birthday', 'corporate', 'proposal', 'custom'] as $type) {
        RentalInquiry::query()->delete();

        postJson('/api/rentals/inquiry', validRentalPayload(['eventType' => $type]))
            ->assertStatus(201);

        expect(RentalInquiry::first()->event_type->value)->toBe($type);
    }
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('returns 422 when required fields missing', function () {
    postJson('/api/rentals/inquiry', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['eventType', 'preferredDate', 'guestCount', 'name', 'email']);
});

test('returns 422 for invalid event type', function () {
    postJson('/api/rentals/inquiry', validRentalPayload(['eventType' => 'invalid_type']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['eventType']);
});

test('returns 422 when preferred date is in the past', function () {
    postJson('/api/rentals/inquiry', validRentalPayload(['preferredDate' => '2020-01-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['preferredDate']);
});

test('returns 422 for guest count below 1', function () {
    postJson('/api/rentals/inquiry', validRentalPayload(['guestCount' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['guestCount']);
});
