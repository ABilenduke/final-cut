<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Filament\Resources\GiftCardResource\Pages\ListGiftCards;
use App\Models\DispatchOutbox;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Services\GiftCardService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Layer B integration test for the admin void flow — exercises the real
 * `GiftCardService` end-to-end: status mutation, ledger write, activity log,
 * and durable finance-notification handoff via `dispatch_outbox`.
 */
beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('void action via Filament writes everything atomically and writes a dispatch_outbox row', function (): void {
    $card = GiftCard::factory()->active()->create([
        'current_balance' => 2500,
        'initial_balance' => 5000,
    ]);

    Livewire::test(ListGiftCards::class)
        ->mountTableAction('void', $card)
        ->set('mountedActions.0.data.reason', 'customer requested refund per duplicate purchase')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $card->refresh();
    expect($card->status)->toBe(GiftCardStatus::Voided)
        ->and($card->current_balance)->toBe(0)
        ->and($card->voided_at)->not->toBeNull()
        ->and($card->voided_reason)->toBe('customer requested refund per duplicate purchase')
        ->and($card->voided_by_admin_user_id)->toBe($this->admin->id);

    $ledger = GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Void)
        ->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->amount_cents)->toBe(-2500)
        ->and($ledger->balance_after_cents)->toBe(0)
        ->and($ledger->admin_user_id)->toBe($this->admin->id);

    expect(Activity::where('log_name', 'admin')
        ->where('description', GiftCardService::EVENT_VOIDED)
        ->where('causer_id', $this->admin->id)
        ->count())->toBe(1);

    // Outbox row carries the full payload the worker needs to dispatch
    // `NotifyFinanceOfGiftCardVoid`. The row is unprocessed at write time —
    // Plan 09's worker drains it.
    $outbox = DispatchOutbox::where('event_type', GiftCardService::EVENT_VOIDED)->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox->payload['gift_card_id'])->toBe($card->id)
        ->and($outbox->payload['reason'])->toBe('customer requested refund per duplicate purchase')
        ->and($outbox->payload['balance_voided'])->toBe(2500)
        ->and($outbox->payload['voided_by_admin_user_id'])->toBe($this->admin->id)
        ->and($outbox->processed_at)->toBeNull()
        ->and($outbox->failed_at)->toBeNull()
        ->and($outbox->attempts)->toBe(0);
});
