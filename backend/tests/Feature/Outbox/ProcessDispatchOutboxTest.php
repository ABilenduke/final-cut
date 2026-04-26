<?php

use App\Jobs\NotifyCustomerOfShowtimeCancellation;
use App\Jobs\NotifyFinanceOfGiftCardVoid;
use App\Models\DispatchOutbox;
use App\Services\GiftCardService;
use App\Services\ShowtimeService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Bus::fake();
});

test('it dispatches the mapped job for a showtime.cancelled row and marks it processed', function (): void {
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => [
            'booking_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'showtime_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        ],
    ]);

    $exit = $this->artisan('outbox:dispatch')->run();

    expect($exit)->toBe(0);

    Bus::assertDispatched(
        NotifyCustomerOfShowtimeCancellation::class,
        fn ($job) => $job->bookingId === '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    );

    $row->refresh();
    expect($row->processed_at)->not->toBeNull()
        ->and($row->failed_at)->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->last_error)->toBeNull();
});

test('it dispatches the mapped job for a gift_card.voided row', function (): void {
    DispatchOutbox::create([
        'event_type' => GiftCardService::EVENT_VOIDED,
        'payload' => [
            'gift_card_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'reason' => 'Test void reason',
            'balance_voided' => 5000,
            'voided_by_admin_user_id' => 1,
        ],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(
        NotifyFinanceOfGiftCardVoid::class,
        fn ($job) => $job->giftCardId === '01ARZ3NDEKTSV4RRFFQ69G5FAV'
            && $job->reason === 'Test void reason'
            && $job->balanceVoided === 5000
            && $job->voidedByAdminUserId === 1,
    );
});

test('it skips rows with available_at in the future', function (): void {
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b1', 'showtime_id' => 's1'],
        'available_at' => now()->addHour(),
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();

    $row->refresh();
    expect($row->processed_at)->toBeNull()
        ->and($row->attempts)->toBe(0);
});

test('it skips rows already processed', function (): void {
    DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b1', 'showtime_id' => 's1'],
        'processed_at' => now(),
        'attempts' => 1,
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();
});

test('it parks rows with unknown event_type and emits an error log', function (): void {
    Log::spy();

    $row = DispatchOutbox::create([
        'event_type' => 'completely.unknown',
        'payload' => ['anything' => true],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();

    $row->refresh();
    expect($row->failed_at)->not->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->last_error)->toContain('Unknown outbox event_type');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'unknown event_type'));
});

test('it parks rows that have hit MAX_ATTEMPTS', function (): void {
    Log::spy();

    // Force the dispatcher to throw by patching the job's queue connection
    // to a non-existent driver — `Bus::fake()` swallows job dispatches, so
    // we instead simulate by giving the row an attempts count one short of
    // the max and a malformed payload that the dispatcher will hit when
    // pulling `booking_id`.
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => [], // missing booking_id → throws on (string) $payload['booking_id']
        'attempts' => DispatchOutbox::MAX_ATTEMPTS - 1,
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    $row->refresh();
    expect($row->attempts)->toBe(DispatchOutbox::MAX_ATTEMPTS)
        ->and($row->failed_at)->not->toBeNull()
        ->and($row->processed_at)->toBeNull();

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'MAX_ATTEMPTS'));
});

test('it processes up to the configured batch size per invocation', function (): void {
    foreach (range(1, 5) as $i) {
        DispatchOutbox::create([
            'event_type' => ShowtimeService::EVENT_CANCELLED,
            'payload' => ['booking_id' => "b{$i}", 'showtime_id' => "s{$i}"],
        ]);
    }

    $this->artisan('outbox:dispatch --batch=2')->assertExitCode(0);

    expect(DispatchOutbox::whereNotNull('processed_at')->count())->toBe(2);
    expect(DispatchOutbox::whereNull('processed_at')->count())->toBe(3);
});

test('outbox:prune deletes processed rows older than the retention window', function (): void {
    $oldProcessed = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'processed_at' => now()->subDays(30),
    ]);

    $recentProcessed = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'processed_at' => now()->subDays(2),
    ]);

    $oldFailed = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'processed_at' => now()->subDays(30),
        'failed_at' => now()->subDays(30),
    ]);

    $this->artisan('outbox:prune')->assertExitCode(0);

    expect(DispatchOutbox::find($oldProcessed->id))->toBeNull()
        ->and(DispatchOutbox::find($recentProcessed->id))->not->toBeNull()
        // Failed rows are retained regardless of age — a human still needs to
        // investigate them, and the prune is silent about parked rows.
        ->and(DispatchOutbox::find($oldFailed->id))->not->toBeNull();
});
