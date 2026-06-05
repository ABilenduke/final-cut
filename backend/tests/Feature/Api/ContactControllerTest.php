<?php

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function validContactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'subject' => 'Question about accessibility',
        'message' => 'Do you offer assisted listening devices?',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

test('submits contact form successfully', function () {
    Log::shouldReceive('info')->once();

    postJson('/api/contact', validContactPayload())
        ->assertOk()
        ->assertJsonPath('data.success', true);
});

test('logs the contact submission with correct data', function () {
    $payload = validContactPayload();

    Log::shouldReceive('info')
        ->once()
        ->withArgs(function (string $message, array $context) use ($payload) {
            return $message === 'Contact form submission'
                && $context['name'] === $payload['name']
                && $context['email'] === $payload['email']
                && $context['subject'] === $payload['subject']
                && $context['message'] === $payload['message'];
        });

    postJson('/api/contact', $payload)
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('returns 422 when required fields missing', function () {
    postJson('/api/contact', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'subject', 'message']);
});

test('returns 422 for invalid email', function () {
    postJson('/api/contact', validContactPayload(['email' => 'not-an-email']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('contact email longer than 255 is rejected', function () {
    // RFC-valid (local part <= 64, short domain labels) but 276 chars, so
    // max:255 is the firing rule — not an RFC-invalidity that would mask it.
    $longValidEmail = str_repeat('a', 64).'@'.str_repeat('sub.', 50).'example.com';

    postJson('/api/contact', validContactPayload(['email' => $longValidEmail]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
