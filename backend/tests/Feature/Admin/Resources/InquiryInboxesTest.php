<?php

use App\Enums\InquiryStatus;
use App\Exceptions\ContactSubmissionException;
use App\Exceptions\InquiryTransitionException;
use App\Filament\Resources\ContactSubmissionResource;
use App\Filament\Resources\ContactSubmissionResource\Pages\ListContactSubmissions;
use App\Filament\Resources\RentalInquiryResource;
use App\Filament\Resources\RentalInquiryResource\Pages\ListRentalInquiries;
use App\Models\ContactSubmission;
use App\Models\RentalInquiry;
use App\Services\ContactSubmissionService;
use App\Services\RentalInquiryService;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\postJson;

// ── Contact form persistence (customer API) ─────────────────────────────────

test('a contact form submission is persisted', function (): void {
    postJson('/api/contact', [
        'name' => 'Curious Customer',
        'email' => 'curious@example.com',
        'subject' => 'Private hire question',
        'message' => 'Do you host birthday screenings on Sundays?',
    ])->assertOk();

    $row = ContactSubmission::first();
    expect($row)->not->toBeNull()
        ->and($row->email)->toBe('curious@example.com')
        ->and($row->handled_at)->toBeNull();
});

// ── Rental inquiries ────────────────────────────────────────────────────────

test('rental inquiries list with a pending badge and transition with activity', function (): void {
    $admin = $this->actingAsAdmin();
    $inquiry = RentalInquiry::factory()->create(['status' => InquiryStatus::Pending]);

    Livewire::test(ListRentalInquiries::class)
        ->assertCanSeeTableRecords([$inquiry]);
    expect(RentalInquiryResource::getNavigationBadge())->toBe('1');

    app(RentalInquiryService::class)->transition($inquiry, InquiryStatus::Contacted, $admin);

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Contacted);
    expect(RentalInquiryResource::getNavigationBadge())->toBeNull();
    expect(Activity::where('log_name', 'admin')
        ->where('description', RentalInquiryService::EVENT_TRANSITIONED)
        ->where('causer_id', $admin->id)
        ->exists())->toBeTrue();
});

test('illegal rental transitions are refused', function (): void {
    $admin = $this->actingAsAdmin();
    $service = app(RentalInquiryService::class);

    $confirmed = RentalInquiry::factory()->create(['status' => InquiryStatus::Confirmed]);

    expect(fn () => $service->transition($confirmed, InquiryStatus::Contacted, $admin))
        ->toThrow(InquiryTransitionException::class, 'cannot move');

    // The UI derives its options from the same map — terminal rows offer none.
    expect(RentalInquiryService::allowedTransitions(InquiryStatus::Confirmed))->toBe([]);
    expect(RentalInquiryService::allowedTransitions(InquiryStatus::Pending))
        ->toContain(InquiryStatus::Contacted);
});

// ── Contact submissions ─────────────────────────────────────────────────────

test('contact submissions can be marked handled exactly once', function (): void {
    $admin = $this->actingAsAdmin();
    $submission = ContactSubmission::factory()->create();

    Livewire::test(ListContactSubmissions::class)
        ->assertCanSeeTableRecords([$submission]);
    expect(ContactSubmissionResource::getNavigationBadge())->toBe('1');

    app(ContactSubmissionService::class)->markHandled($submission, $admin);

    $submission->refresh();
    expect($submission->handled_at)->not->toBeNull()
        ->and($submission->handled_by)->toBe($admin->id);
    expect(ContactSubmissionResource::getNavigationBadge())->toBeNull();

    expect(fn () => app(ContactSubmissionService::class)->markHandled($submission->refresh(), $admin))
        ->toThrow(ContactSubmissionException::class, 'already');
});

// ── Permissions ─────────────────────────────────────────────────────────────

test('ops can view both inboxes but cannot act; roleless admins are denied', function (): void {
    $this->actingAsOps();
    $inquiry = RentalInquiry::factory()->create(['status' => InquiryStatus::Pending]);
    $submission = ContactSubmission::factory()->create();

    expect(RentalInquiryResource::canViewAny())->toBeTrue();
    expect(ContactSubmissionResource::canViewAny())->toBeTrue();

    Livewire::test(ListRentalInquiries::class)
        ->assertTableActionHidden('set_status', $inquiry);
    Livewire::test(ListContactSubmissions::class)
        ->assertTableActionHidden('mark_handled', $submission);

    $this->actingAsNobody();
    expect(RentalInquiryResource::canViewAny())->toBeFalse();
    expect(ContactSubmissionResource::canViewAny())->toBeFalse();
});

test('a manager can transition from the table action', function (): void {
    $this->actingAsManager();
    $inquiry = RentalInquiry::factory()->create(['status' => InquiryStatus::Pending]);

    Livewire::test(ListRentalInquiries::class)
        ->mountTableAction('set_status', $inquiry)
        ->set('mountedActions.0.data.status', InquiryStatus::Declined->value)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($inquiry->refresh()->status)->toBe(InquiryStatus::Declined);
});
