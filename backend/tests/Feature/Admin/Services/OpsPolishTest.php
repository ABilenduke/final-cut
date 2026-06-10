<?php

use App\Enums\BookingStatus;
use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotAdjustableException;
use App\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Filament\Resources\GiftCardResource\Pages\ListGiftCards;
use App\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\PromoCode;
use App\Services\GiftCardService;
use App\Services\PromoCodeService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Helpers\BookingTestHelper;

uses(BookingTestHelper::class);

// ── Gift card balance adjustment ────────────────────────────────────────

test('adjust credits the balance, writes an Adjustment ledger row, and logs activity', function (): void {
    $admin = $this->actingAsAdmin();
    $card = GiftCard::factory()->create([
        'status' => GiftCardStatus::Active,
        'initial_balance' => 5000,
        'current_balance' => 2000,
    ]);

    app(GiftCardService::class)->adjust($card, 500, 'Support compensation for failed redemption', $admin);

    expect($card->refresh()->current_balance)->toBe(2500);

    $entry = GiftCardLedgerEntry::query()
        ->where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Adjustment)
        ->first();
    expect($entry)->not->toBeNull()
        ->and($entry->amount_cents)->toBe(500)
        ->and($entry->balance_after_cents)->toBe(2500)
        ->and($entry->admin_user_id)->toBe($admin->id);

    expect(Activity::query()->where('description', 'gift_card.adjusted')->exists())->toBeTrue();
});

test('adjust can deduct, and depleting to zero flips the status', function (): void {
    $admin = $this->actingAsAdmin();
    $card = GiftCard::factory()->create([
        'status' => GiftCardStatus::Active,
        'initial_balance' => 5000,
        'current_balance' => 700,
    ]);

    app(GiftCardService::class)->adjust($card, -700, 'Correcting a double credit', $admin);

    expect($card->refresh()->current_balance)->toBe(0)
        ->and($card->status)->toBe(GiftCardStatus::Depleted);
});

test('a positive adjustment re-activates a depleted card', function (): void {
    $admin = $this->actingAsAdmin();
    $card = GiftCard::factory()->create([
        'status' => GiftCardStatus::Depleted,
        'initial_balance' => 5000,
        'current_balance' => 0,
    ]);

    app(GiftCardService::class)->adjust($card, 1000, 'Goodwill credit after complaint', $admin);

    expect($card->refresh()->current_balance)->toBe(1000)
        ->and($card->status)->toBe(GiftCardStatus::Active);
});

test('adjust rejects overdraw, zero deltas, and voided or expired cards', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(GiftCardService::class);

    $card = GiftCard::factory()->create([
        'status' => GiftCardStatus::Active,
        'initial_balance' => 5000,
        'current_balance' => 300,
    ]);

    expect(fn () => $service->adjust($card, -400, 'Overdraw attempt', $admin))
        ->toThrow(GiftCardNotAdjustableException::class);
    expect(fn () => $service->adjust($card, 0, 'No-op', $admin))
        ->toThrow(GiftCardNotAdjustableException::class);

    $voided = GiftCard::factory()->create(['status' => GiftCardStatus::Voided, 'current_balance' => 0]);
    expect(fn () => $service->adjust($voided, 500, 'Should fail', $admin))
        ->toThrow(GiftCardNotAdjustableException::class);

    $expired = GiftCard::factory()->create(['status' => GiftCardStatus::Expired, 'current_balance' => 500]);
    expect(fn () => $service->adjust($expired, 500, 'Should fail', $admin))
        ->toThrow(GiftCardNotAdjustableException::class);

    expect($card->refresh()->current_balance)->toBe(300);
});

test('the adjust table action routes through the service', function (): void {
    $this->actingAsAdmin();
    $card = GiftCard::factory()->create([
        'status' => GiftCardStatus::Active,
        'initial_balance' => 5000,
        'current_balance' => 2000,
    ]);

    Livewire::test(ListGiftCards::class)
        ->mountTableAction('adjust_balance', $card)
        ->set('mountedActions.0.data.amount_cents', 250)
        ->set('mountedActions.0.data.reason', 'Support compensation per ticket 4821')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($card->refresh()->current_balance)->toBe(2250);
});

test('ops cannot see the adjust action', function (): void {
    $this->actingAsOps();
    $card = GiftCard::factory()->create(['status' => GiftCardStatus::Active, 'current_balance' => 2000]);

    Livewire::test(ListGiftCards::class)
        ->assertTableActionHidden('adjust_balance', $card);
});

// ── Promo code reactivation ─────────────────────────────────────────────

test('reactivate clears deactivated_at and logs activity', function (): void {
    $admin = $this->actingAsAdmin();
    $promo = PromoCode::factory()->create(['deactivated_at' => now()->subDay()]);

    app(PromoCodeService::class)->reactivate($promo, $admin);

    expect($promo->refresh()->deactivated_at)->toBeNull()
        ->and($promo->is_active)->toBeTrue()
        ->and(Activity::query()->where('description', 'promo_code.reactivated')->exists())->toBeTrue();
});

test('the reactivate table action shows only for deactivated promos and works', function (): void {
    $this->actingAsAdmin();
    $active = PromoCode::factory()->create(['deactivated_at' => null]);
    $inactive = PromoCode::factory()->create(['deactivated_at' => now()]);

    Livewire::test(ListPromoCodes::class)
        ->assertTableActionHidden('reactivate', $active)
        ->callTableAction('reactivate', $inactive);

    expect($inactive->refresh()->is_active)->toBeTrue();
});

test('ops cannot see the reactivate action', function (): void {
    $this->actingAsOps();
    $promo = PromoCode::factory()->create(['deactivated_at' => now()]);

    Livewire::test(ListPromoCodes::class)
        ->assertTableActionHidden('reactivate', $promo);
});

// ── Refund visibility on the booking view ───────────────────────────────

test('the booking view surfaces refund timestamps and the stripe refund id', function (): void {
    $this->actingAsAdmin();
    ['showtime' => $showtime, 'seats' => $seats] = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'status' => BookingStatus::Refunded,
        'refund_initiated_at' => now()->subMinutes(5),
        'refunded_at' => now(),
        'stripe_refund_id' => 're_test_12345',
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
        ->assertSee('Refund initiated')
        ->assertSee('Refunded at')
        ->assertSee('Stripe refund')
        ->assertSee('re_test_12345');
});

test('the refund fields stay hidden on bookings that were never refunded', function (): void {
    $this->actingAsAdmin();
    ['showtime' => $showtime] = $this->createShowtimeWithSeats();

    $booking = Booking::factory()->create([
        'showtime_id' => $showtime->id,
        'refund_initiated_at' => null,
        'refunded_at' => null,
        'stripe_refund_id' => null,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->getKey()])
        ->assertDontSee('Refund initiated')
        ->assertDontSee('Stripe refund');
});
