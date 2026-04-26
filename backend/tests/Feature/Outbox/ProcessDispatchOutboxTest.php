<?php

use App\Jobs\NotifyCustomerOfShowtimeCancellation;
use App\Jobs\NotifyFinanceOfGiftCardVoid;
use App\Models\DispatchOutbox;
use App\Outbox\OutboxDispatcher;
use App\Services\GiftCardService;
use App\Services\ShowtimeService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Bus::fake();
});

afterEach(function (): void {
    // Some tests below rebind OutboxDispatcher to a stub that throws.
    // Pest/Laravel container reset between tests is not always exhaustive
    // for runtime-bound singletons in this version, so explicitly forget
    // the binding to keep the rest of the file deterministic.
    $this->app->forgetInstance(OutboxDispatcher::class);
    $this->app->offsetUnset(OutboxDispatcher::class);
});

test('it dispatches the mapped job for a showtime.cancelled row and marks it processed', function (): void {
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => [
            'booking_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'showtime_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAW',
        ],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(
        NotifyCustomerOfShowtimeCancellation::class,
        fn ($job) => $job->bookingId === '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    );

    $row->refresh();
    expect($row->processed_at)->not->toBeNull()
        ->and($row->failed_at)->toBeNull()
        // attempts is bumped at claim time (before dispatch) so a crash
        // mid-dispatch leaves the row visibly in-flight.
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

test('it skips parked rows (failed_at not null)', function (): void {
    // Parked rows must never be re-selected — the dispatcher would just
    // re-park them and spam the error log. The dispatchable() scope is
    // explicit about excluding them.
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b1', 'showtime_id' => 's1'],
        'failed_at' => now()->subMinute(),
        'attempts' => 1,
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();

    $row->refresh();
    expect($row->attempts)->toBe(1); // attempts NOT bumped — row not claimed
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
        ->withArgs(fn ($message) => str_contains($message, 'developer error'));
});

test('it parks rows with malformed payloads as a developer error', function (): void {
    Log::spy();

    // Missing required key (booking_id) on a known event_type. The
    // dispatcher's requirePayloadValue() throws InvalidArgumentException,
    // which the processor treats the same as an unknown event_type:
    // park immediately, don't cycle through retries.
    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => [],
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertNothingDispatched();

    $row->refresh();
    expect($row->failed_at)->not->toBeNull()
        ->and($row->attempts)->toBe(1)
        ->and($row->last_error)->toContain('Malformed outbox payload')
        ->and($row->last_error)->toContain('booking_id');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'developer error'));
});

test('it parks rows that have hit MAX_ATTEMPTS via transient failures', function (): void {
    Log::spy();

    // Bind a stub dispatcher that throws a generic RuntimeException so
    // the processor walks the Throwable path (not the
    // InvalidArgumentException park path). This simulates a transient
    // failure (Redis outage, mail driver down, etc.) repeatedly.
    $this->app->bind(OutboxDispatcher::class, function () {
        return new class extends OutboxDispatcher
        {
            public function dispatch(DispatchOutbox $row): void
            {
                throw new RuntimeException('Simulated transient failure');
            }
        };
    });

    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'attempts' => DispatchOutbox::MAX_ATTEMPTS - 1,
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    $row->refresh();
    expect($row->attempts)->toBe(DispatchOutbox::MAX_ATTEMPTS)
        ->and($row->failed_at)->not->toBeNull()
        ->and($row->processed_at)->toBeNull()
        ->and($row->last_error)->toContain('Simulated transient failure');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'MAX_ATTEMPTS'));
});

test('a transient failure under MAX_ATTEMPTS leaves the row available for retry', function (): void {
    $this->app->bind(OutboxDispatcher::class, function () {
        return new class extends OutboxDispatcher
        {
            public function dispatch(DispatchOutbox $row): void
            {
                throw new RuntimeException('Will be retried');
            }
        };
    });

    $row = DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'attempts' => 0,
    ]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    $row->refresh();
    expect($row->attempts)->toBe(1)
        ->and($row->failed_at)->toBeNull()
        ->and($row->processed_at)->toBeNull()
        ->and($row->last_error)->toContain('Will be retried');
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

test('outbox:dispatch rejects non-positive or non-numeric --batch', function (): void {
    DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
    ]);

    // Exit code INVALID (= 2 from Symfony Console). Without the guard,
    // `(int) 'abc'` is 0 → silent no-op; `--batch=-1` would be passed
    // through to limit() and trigger driver-specific behavior.
    $this->artisan('outbox:dispatch --batch=0')->assertExitCode(2);
    $this->artisan('outbox:dispatch --batch=-1')->assertExitCode(2);
    $this->artisan('outbox:dispatch --batch=abc')->assertExitCode(2);

    // No row was claimed (attempts stays at 0) — the guard ran before
    // the dispatch transaction.
    expect(DispatchOutbox::sole()->attempts)->toBe(0);
});

test('outbox:prune rejects zero days', function (): void {
    DispatchOutbox::create([
        'event_type' => ShowtimeService::EVENT_CANCELLED,
        'payload' => ['booking_id' => 'b', 'showtime_id' => 's'],
        'processed_at' => now()->subDays(30),
    ]);

    // Exit code INVALID (= 2 from Symfony Console) — anything non-zero
    // signals failure to the scheduler / a CI runner.
    $this->artisan('outbox:prune --days=0')->assertExitCode(2);
    $this->artisan('outbox:prune --days=-7')->assertExitCode(2);

    // Nothing was deleted — the guard ran before any DB write.
    expect(DispatchOutbox::count())->toBe(1);
});
