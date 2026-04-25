<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotVoidableException;
use App\Filament\Resources\GiftCardResource;
use App\Filament\Resources\GiftCardResource\Pages\ListGiftCards;
use App\Filament\Resources\GiftCardResource\RelationManagers\BalanceHistoryRelationManager;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = $this->actingAsAdmin();
});

test('admin can see the gift card list', function (): void {
    $cards = GiftCard::factory()->count(3)->create();

    Livewire::test(ListGiftCards::class)
        ->assertCanSeeTableRecords($cards);
});

test('list searchable by code', function (): void {
    GiftCard::factory()->create(['code' => 'GC-FINDABLE']);
    GiftCard::factory()->count(2)->create();

    Livewire::test(ListGiftCards::class)
        ->searchTable('GC-FINDABLE')
        ->assertCanSeeTableRecords(GiftCard::where('code', 'GC-FINDABLE')->get());
});

test('list searchable by recipient email', function (): void {
    GiftCard::factory()->create(['recipient_email' => 'find-me@example.com']);
    GiftCard::factory()->count(2)->create();

    Livewire::test(ListGiftCards::class)
        ->searchTable('find-me@example.com')
        ->assertCanSeeTableRecords(GiftCard::where('recipient_email', 'find-me@example.com')->get());
});

test('void action visible only for active cards', function (): void {
    $active = GiftCard::factory()->active()->create();
    $depleted = GiftCard::factory()->depleted()->create();

    Livewire::test(ListGiftCards::class)
        ->assertTableActionVisible('void', $active)
        ->assertTableActionHidden('void', $depleted);
});

test('void action requires reason of at least 20 chars', function (): void {
    $card = GiftCard::factory()->active()->create();

    $service = $this->mock(GiftCardService::class);
    $service->shouldNotReceive('void');

    Livewire::test(ListGiftCards::class)
        ->mountTableAction('void', $card)
        ->set('mountedActions.0.data.reason', 'too short')
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['reason']);
});

test('void action calls GiftCardService with the admin actor', function (): void {
    Mail::fake();
    $card = GiftCard::factory()->active()->create(['current_balance' => 1000, 'initial_balance' => 1000]);

    Livewire::test(ListGiftCards::class)
        ->mountTableAction('void', $card)
        ->set('mountedActions.0.data.reason', 'customer requested refund per ticket')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $card->refresh();
    expect($card->status)->toBe(GiftCardStatus::Voided)
        ->and($card->voided_by_admin_user_id)->toBe($this->admin->id)
        ->and($card->voided_reason)->toBe('customer requested refund per ticket');
});

test('void surfaces a notification when service throws GiftCardNotVoidableException for already_voided', function (): void {
    $card = GiftCard::factory()->voided()->create();

    // Action is hidden for voided cards via `visible()` guard, so trigger
    // the action by mocking `visible` away — assert the service-thrown
    // exception is caught and surfaced.
    // Easier: mock service to throw, and force-call action ignoring visibility.
    $card->status = GiftCardStatus::Active; // bypass UI guard for this scenario
    $card->save();

    $service = $this->mock(GiftCardService::class);
    $service->shouldReceive('void')
        ->once()
        ->andThrow(new GiftCardNotVoidableException(GiftCardNotVoidableException::REASON_ALREADY_VOIDED));

    Livewire::test(ListGiftCards::class)
        ->mountTableAction('void', $card)
        ->set('mountedActions.0.data.reason', 'attempting void on race-already-voided')
        ->callMountedTableAction()
        ->assertNotified('Gift card cannot be voided');
});

test('GiftCard ledgerEntries relation orders entries reverse-chronological for the relation manager', function (): void {
    $card = GiftCard::factory()->active()->create();

    $older = GiftCardLedgerEntry::factory()->create([
        'gift_card_id' => $card->id,
        'type' => GiftCardLedgerType::Purchase,
        'amount_cents' => 5000,
        'balance_after_cents' => 5000,
        'created_at' => now()->subHour(),
    ]);
    $newer = GiftCardLedgerEntry::factory()->create([
        'gift_card_id' => $card->id,
        'type' => GiftCardLedgerType::Redemption,
        'amount_cents' => -1500,
        'balance_after_cents' => 3500,
        'created_at' => now(),
    ]);

    $entries = $card->ledgerEntries()->get();
    expect($entries->pluck('id')->all())->toBe([$newer->id, $older->id]);

    // Sanity-check the resource registers the relation manager.
    expect(GiftCardResource::getRelations())->toBe([BalanceHistoryRelationManager::class]);
});

test('canCreate / canEdit / canDelete are all false', function (): void {
    $card = GiftCard::factory()->create();

    expect(GiftCardResource::canCreate())->toBeFalse();
    expect(GiftCardResource::canEdit($card))->toBeFalse();
    expect(GiftCardResource::canDelete($card))->toBeFalse();
});
