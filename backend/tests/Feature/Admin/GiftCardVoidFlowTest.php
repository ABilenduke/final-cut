<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Filament\Resources\GiftCardResource\Pages\ListGiftCards;
use App\Mail\GiftCardVoidedMail;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * Layer B integration test for the admin void flow — exercises the real
 * `GiftCardService` end-to-end: status mutation, ledger write, activity log,
 * and queued finance mail.
 */
beforeEach(function (): void {
    Mail::fake();
    $this->admin = $this->actingAsAdmin();
});

test('void action via Filament writes everything atomically and queues finance mail', function (): void {
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
        ->where('description', 'gift_card.voided')
        ->where('causer_id', $this->admin->id)
        ->count())->toBe(1);

    Mail::assertQueued(GiftCardVoidedMail::class, function ($mail) use ($card) {
        return $mail->giftCard->id === $card->id
            && $mail->balanceVoided === 2500
            && $mail->by?->id === $this->admin->id
            && $mail->hasTo(config('finance.notification_email'));
    });
});
