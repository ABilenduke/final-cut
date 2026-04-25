<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotVoidableException;
use App\Mail\GiftCardVoidedMail;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Booking;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    Mail::fake();
    $this->service = app(GiftCardService::class);
});

function buildBookingForLedger(): Booking
{
    $location = Location::factory()->create();
    $auditorium = Auditorium::factory()->create(['location_id' => $location->id]);
    AuditoriumSection::factory()->create(['auditorium_id' => $auditorium->id]);
    $movie = Movie::factory()->create();
    $showtime = Showtime::factory()->create([
        'movie_id' => $movie->id,
        'auditorium_id' => $auditorium->id,
    ]);

    return Booking::factory()->create(['showtime_id' => $showtime->id]);
}

test('purchase writes a Purchase ledger entry and no activity when actor is null', function (): void {
    $giftCard = $this->service->purchase([
        'code' => 'GC-UNIT01',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'recipient_email' => 'r@example.com',
        'recipient_name' => 'R',
        'sender_name' => 'S',
        'message' => null,
        'status' => GiftCardStatus::Active,
        'purchased_at' => now(),
    ], null);

    $ledger = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)->first();
    expect($ledger->type)->toBe(GiftCardLedgerType::Purchase)
        ->and($ledger->amount_cents)->toBe(5000)
        ->and($ledger->balance_after_cents)->toBe(5000);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('purchase writes activity when actor is set', function (): void {
    $admin = AdminUser::factory()->create();

    $this->service->purchase([
        'code' => 'GC-UNIT02',
        'initial_balance' => 2500,
        'current_balance' => 2500,
        'recipient_email' => 'r@example.com',
        'recipient_name' => 'R',
        'sender_name' => 'S',
        'status' => GiftCardStatus::Active,
        'purchased_at' => now(),
    ], $admin);

    expect(Activity::where('description', GiftCardService::EVENT_PURCHASED)->count())->toBe(1);
});

test('redeemAgainstBooking deducts balance and writes Redemption ledger entry', function (): void {
    $giftCard = GiftCard::factory()->create(['current_balance' => 1000, 'initial_balance' => 1000]);
    $booking = buildBookingForLedger();

    $this->service->redeemAgainstBooking($giftCard, 400, $booking, null);

    expect($giftCard->current_balance)->toBe(600)
        ->and($giftCard->status)->toBe(GiftCardStatus::Active);

    $ledger = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)->first();
    expect($ledger->type)->toBe(GiftCardLedgerType::Redemption)
        ->and($ledger->amount_cents)->toBe(-400)
        ->and($ledger->balance_after_cents)->toBe(600)
        ->and($ledger->booking_id)->toBe($booking->id);

    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('redeemAgainstBooking transitions to Depleted when balance hits zero', function (): void {
    $giftCard = GiftCard::factory()->create(['current_balance' => 500, 'initial_balance' => 500]);
    $booking = buildBookingForLedger();

    $this->service->redeemAgainstBooking($giftCard, 500, $booking, null);

    expect($giftCard->current_balance)->toBe(0)
        ->and($giftCard->status)->toBe(GiftCardStatus::Depleted);
});

test('findByCode returns the gift card', function (): void {
    $giftCard = GiftCard::factory()->create(['code' => 'GC-FINDME']);

    expect($this->service->findByCode('GC-FINDME')->id)->toBe($giftCard->id);
    expect($this->service->findByCode('GC-NOTHERE'))->toBeNull();
});

test('getBalance returns current_balance', function (): void {
    $giftCard = GiftCard::factory()->create(['current_balance' => 7500]);
    expect($this->service->getBalance($giftCard))->toBe(7500);
});

test('void success mutates status, writes ledger, activity, and queues mail', function (): void {
    $admin = AdminUser::factory()->create();
    $giftCard = GiftCard::factory()->active()->create(['current_balance' => 2000, 'initial_balance' => 2000]);

    $this->service->void($giftCard, 'customer requested refund due to duplicate purchase', $admin);

    $giftCard->refresh();
    expect($giftCard->status)->toBe(GiftCardStatus::Voided)
        ->and($giftCard->current_balance)->toBe(0)
        ->and($giftCard->voided_at)->not->toBeNull()
        ->and($giftCard->voided_by_admin_user_id)->toBe($admin->id);

    $ledger = GiftCardLedgerEntry::where('gift_card_id', $giftCard->id)
        ->where('type', GiftCardLedgerType::Void)
        ->first();
    expect($ledger)->not->toBeNull()
        ->and($ledger->amount_cents)->toBe(-2000)
        ->and($ledger->balance_after_cents)->toBe(0)
        ->and($ledger->admin_user_id)->toBe($admin->id);

    expect(Activity::where('description', GiftCardService::EVENT_VOIDED)->count())->toBe(1);

    Mail::assertQueued(GiftCardVoidedMail::class, fn ($mail) => $mail->giftCard->id === $giftCard->id
        && $mail->balanceVoided === 2000
        && $mail->by?->id === $admin->id);
});

test('void on a voided card throws GiftCardNotVoidableException::REASON_ALREADY_VOIDED', function (): void {
    $admin = AdminUser::factory()->create();
    $giftCard = GiftCard::factory()->voided()->create();

    $caught = null;
    try {
        $this->service->void($giftCard, 'a reason that is definitely long enough', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught)->not->toBeNull()
        ->and($caught->reason)->toBe(GiftCardNotVoidableException::REASON_ALREADY_VOIDED);

    Mail::assertNothingQueued();
});

test('void on a depleted card throws REASON_DEPLETED', function (): void {
    $admin = AdminUser::factory()->create();
    $giftCard = GiftCard::factory()->depleted()->create();

    $caught = null;
    try {
        $this->service->void($giftCard, 'reason long enough for the void flow', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught->reason)->toBe(GiftCardNotVoidableException::REASON_DEPLETED);
});

test('void on an expired card throws REASON_EXPIRED', function (): void {
    $admin = AdminUser::factory()->create();
    $giftCard = GiftCard::factory()->expired()->create();

    $caught = null;
    try {
        $this->service->void($giftCard, 'reason long enough for the void flow', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught->reason)->toBe(GiftCardNotVoidableException::REASON_EXPIRED);
});
