<?php

use App\Exceptions\OutboxRetryException;
use App\Filament\Resources\DispatchOutboxResource;
use App\Filament\Resources\DispatchOutboxResource\Pages\ListDispatchOutbox;
use App\Jobs\SendBookingRefundConfirmation;
use App\Models\DispatchOutbox;
use App\Services\BookingRefundService;
use App\Services\OutboxRetryService;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function parkedRowFixture(): DispatchOutbox
{
    return DispatchOutbox::create([
        'event_type' => BookingRefundService::EVENT_REFUNDED,
        'payload' => ['booking_id' => 'b-1', 'card_refund' => 1200, 'gift_restored' => 0],
        'attempts' => DispatchOutbox::MAX_ATTEMPTS,
        'failed_at' => now()->subHour(),
        'last_error' => 'SMTP connection refused',
    ]);
}

test('admins can list outbox rows with derived statuses; the nav badge counts parked rows', function (): void {
    $this->actingAsAdmin();

    $parked = parkedRowFixture();
    $pending = DispatchOutbox::create(['event_type' => 'x.pending', 'payload' => []]);
    $processed = DispatchOutbox::create(['event_type' => 'x.done', 'payload' => [], 'processed_at' => now()]);

    Livewire::test(ListDispatchOutbox::class)
        ->assertCanSeeTableRecords([$parked, $pending, $processed]);

    expect(DispatchOutboxResource::getNavigationBadge())->toBe('1');
});

test('manager and ops roles are denied', function (): void {
    $this->actingAsManager();
    expect(DispatchOutboxResource::canViewAny())->toBeFalse();

    $this->actingAsOps();
    expect(DispatchOutboxResource::canViewAny())->toBeFalse();
    Livewire::test(ListDispatchOutbox::class)->assertForbidden();
});

test('retrying a parked row resets it, logs activity, and the next worker tick processes it', function (): void {
    Bus::fake();
    $admin = $this->actingAsAdmin();
    $row = parkedRowFixture();

    Livewire::test(ListDispatchOutbox::class)
        ->assertTableActionVisible('retry', $row)
        ->callTableAction('retry', $row)
        ->assertHasNoTableActionErrors();

    $row->refresh();
    expect($row->failed_at)->toBeNull()
        ->and($row->attempts)->toBe(0)
        ->and($row->last_error)->toBeNull();

    $activity = Activity::where('log_name', 'admin')
        ->where('description', OutboxRetryService::EVENT_RETRIED)
        ->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($admin->id);

    // Deterministic visibility for the worker (transaction-pinned NOW()).
    DispatchOutbox::whereKey($row->id)->update(['available_at' => now()->subMinute()]);

    $this->artisan('outbox:dispatch')->assertExitCode(0);

    Bus::assertDispatched(SendBookingRefundConfirmation::class, fn ($job) => $job->bookingId === 'b-1');
    expect($row->refresh()->processed_at)->not->toBeNull();
});

test('pending and processed rows cannot be retried', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(OutboxRetryService::class);

    $pending = DispatchOutbox::create(['event_type' => 'x.pending', 'payload' => []]);
    $processed = DispatchOutbox::create(['event_type' => 'x.done', 'payload' => [], 'processed_at' => now()]);

    expect(fn () => $service->retry($pending, $admin))
        ->toThrow(OutboxRetryException::class, 'parked');
    expect(fn () => $service->retry($processed, $admin))
        ->toThrow(OutboxRetryException::class, 'parked');

    Livewire::test(ListDispatchOutbox::class)
        ->assertTableActionHidden('retry', $pending)
        ->assertTableActionHidden('retry', $processed);
});
