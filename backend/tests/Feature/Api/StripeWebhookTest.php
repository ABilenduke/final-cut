<?php

use App\Jobs\CheckOrphanedCharge;
use App\Mail\OrphanedChargeMail;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Outbox\OutboxDispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\BookingTestHelper;

use function Pest\Laravel\call;

uses(BookingTestHelper::class);

const WEBHOOK_SECRET = 'whsec_test_secret';

beforeEach(function (): void {
    config(['services.stripe.webhook_secret' => WEBHOOK_SECRET]);
});

/** Build a signed Stripe webhook request (Stripe's v1 HMAC scheme). */
function postWebhook(array $event)
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", WEBHOOK_SECRET);

    return call(
        'POST',
        '/api/webhooks/stripe',
        [],
        [],
        [],
        [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload,
    );
}

function succeededEvent(string $paymentIntentId): array
{
    return [
        'id' => 'evt_test_1',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => $paymentIntentId,
                'object' => 'payment_intent',
                'amount' => 4200,
                'currency' => 'usd',
            ],
        ],
    ];
}

test('an invalid signature is rejected with 400 and writes nothing', function (): void {
    $payload = json_encode(succeededEvent('pi_bad_sig'));

    call('POST', '/api/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 't=123,v1=not-a-real-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertStatus(400);

    expect(DispatchOutbox::query()->count())->toBe(0);
});

test('a succeeded event matching an existing booking is acknowledged without an orphan check', function (): void {
    ['showtime' => $showtime] = $this->createShowtimeWithSeats();
    Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'stripe_payment_intent_id' => 'pi_known_123',
    ]);

    postWebhook(succeededEvent('pi_known_123'))->assertOk();

    expect(DispatchOutbox::query()->where('event_type', 'payment.orphan_check')->count())->toBe(0);
});

test('a succeeded event with no matching record schedules a deferred orphan check, once', function (): void {
    postWebhook(succeededEvent('pi_orphan_999'))->assertOk();
    postWebhook(succeededEvent('pi_orphan_999'))->assertOk();

    $rows = DispatchOutbox::query()->where('event_type', 'payment.orphan_check')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->payload['payment_intent_id'])->toBe('pi_orphan_999')
        ->and($rows[0]->available_at->gt(now()->addMinutes(20)))->toBeTrue();
});

test('unhandled event types are acknowledged', function (): void {
    postWebhook([
        'id' => 'evt_test_2',
        'type' => 'charge.refunded',
        'data' => ['object' => ['id' => 'ch_1', 'object' => 'charge']],
    ])->assertOk();

    expect(DispatchOutbox::query()->count())->toBe(0);
});

test('the dispatcher maps orphan-check rows to the job', function (): void {
    Queue::fake();

    $row = DispatchOutbox::create([
        'event_type' => 'payment.orphan_check',
        'payload' => ['payment_intent_id' => 'pi_map', 'amount' => 4200, 'currency' => 'usd'],
        'available_at' => now(),
    ]);

    app(OutboxDispatcher::class)->dispatch($row);

    Queue::assertPushed(CheckOrphanedCharge::class, fn ($job) => $job->paymentIntentId === 'pi_map');
});

test('the orphan-check job alerts finance only when the charge is still unmatched', function (): void {
    Mail::fake();
    ['showtime' => $showtime] = $this->createShowtimeWithSeats();

    // Booking appeared by run time (in-band finalize won the race) — no alert.
    Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'stripe_payment_intent_id' => 'pi_recovered',
    ]);
    (new CheckOrphanedCharge('pi_recovered', 4200, 'usd'))->handle();
    Mail::assertNothingOutgoing();

    // Still unmatched and no pending 3DS state — alert finance.
    (new CheckOrphanedCharge('pi_truly_lost', 4200, 'usd'))->handle();
    Mail::assertQueued(OrphanedChargeMail::class, function (OrphanedChargeMail $mail) {
        return $mail->hasTo(config('finance.notification_email'))
            && $mail->paymentIntentId === 'pi_truly_lost';
    });
});

test('the orphan-check job stays quiet while a 3DS pending state exists', function (): void {
    Mail::fake();
    cache()->put('pending_booking:pi_in_3ds', ['anything'], now()->addMinutes(15));

    (new CheckOrphanedCharge('pi_in_3ds', 4200, 'usd'))->handle();

    Mail::assertNothingOutgoing();
});
