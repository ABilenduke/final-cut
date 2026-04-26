<?php

use App\Enums\GiftCardLedgerType;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotVoidableException;
use App\Models\AdminUser;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Booking;
use App\Models\DispatchOutbox;
use App\Models\GiftCard;
use App\Models\GiftCardLedgerEntry;
use App\Models\Location;
use App\Models\Movie;
use App\Models\Showtime;
use App\Services\GiftCardService;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Layer B service-level integration tests. These exercise the real
 * `GiftCardService` against the real DB and prove the invariants that
 * Layer A (Resource, service mocked) cannot.
 */
beforeEach(function (): void {
    $this->service = app(GiftCardService::class);
});

function intBookingFixture(): Booking
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

test('purchase with null actor writes ledger entry and no activity log row', function (): void {
    $this->service->purchase([
        'code' => 'GC-INT01',
        'initial_balance' => 5000,
        'current_balance' => 5000,
        'recipient_email' => 'r@example.com',
        'recipient_name' => 'R',
        'sender_name' => 'S',
        'message' => null,
        'status' => GiftCardStatus::Active,
        'purchased_at' => now(),
    ], null);

    expect(GiftCardLedgerEntry::where('type', GiftCardLedgerType::Purchase)->count())->toBe(1);
    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('redeemAgainstBooking with null actor writes ledger entry and no activity log row', function (): void {
    $card = GiftCard::factory()->create(['current_balance' => 1000, 'initial_balance' => 1000]);
    $booking = intBookingFixture();

    $this->service->redeemAgainstBooking($card, 400, $booking, null);

    expect(GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Redemption)->count())->toBe(1);
    expect(Activity::where('log_name', 'admin')->count())->toBe(0);
});

test('void with admin actor writes status, ledger, activity, and dispatch_outbox row in one transaction', function (): void {
    $admin = AdminUser::factory()->create();
    $card = GiftCard::factory()->active()->create(['current_balance' => 2000, 'initial_balance' => 2000]);

    $this->service->void($card, 'customer requested refund per duplicate purchase', $admin);

    $card->refresh();
    expect($card->status)->toBe(GiftCardStatus::Voided);
    expect(GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Void)->count())->toBe(1);
    expect(Activity::where('description', GiftCardService::EVENT_VOIDED)
        ->where('causer_id', $admin->id)->count())->toBe(1);

    expect(DispatchOutbox::where('event_type', GiftCardService::EVENT_VOIDED)
        ->whereNull('processed_at')
        ->count())->toBe(1);
});

test('void on Voided card throws REASON_ALREADY_VOIDED — no second ledger or outbox row', function (): void {
    $admin = AdminUser::factory()->create();
    $card = GiftCard::factory()->voided()->create();

    $caught = null;
    try {
        $this->service->void($card, 'attempting double-void after concurrent admin action', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(GiftCardNotVoidableException::REASON_ALREADY_VOIDED);
    expect(GiftCardLedgerEntry::where('gift_card_id', $card->id)
        ->where('type', GiftCardLedgerType::Void)->count())->toBe(0);
    expect(DispatchOutbox::where('event_type', GiftCardService::EVENT_VOIDED)->count())->toBe(0);
});

test('void on Depleted card throws REASON_DEPLETED', function (): void {
    $admin = AdminUser::factory()->create();
    $card = GiftCard::factory()->depleted()->create();

    $caught = null;
    try {
        $this->service->void($card, 'attempting void on depleted card for finance audit', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(GiftCardNotVoidableException::REASON_DEPLETED);
});

test('void on Expired card throws REASON_EXPIRED', function (): void {
    $admin = AdminUser::factory()->create();
    $card = GiftCard::factory()->expired()->create();

    $caught = null;
    try {
        $this->service->void($card, 'attempting void on expired card for finance audit', $admin);
    } catch (GiftCardNotVoidableException $e) {
        $caught = $e;
    }

    expect($caught?->reason)->toBe(GiftCardNotVoidableException::REASON_EXPIRED);
});

test('forced rollback inside void transaction leaves no orphaned ledger rows', function (): void {
    $admin = AdminUser::factory()->create();
    $card = GiftCard::factory()->active()->create();

    DB::beginTransaction();
    try {
        $this->service->void($card, 'rollback test for ledger atomicity invariant', $admin);
        DB::rollBack();
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }

    expect(GiftCardLedgerEntry::where('gift_card_id', $card->id)->count())->toBe(0);
    expect($card->fresh()->status)->toBe(GiftCardStatus::Active);
});
