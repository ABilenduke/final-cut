<?php

use App\Enums\InquiryStatus;
use App\Enums\RentalEventType;
use App\Models\RentalInquiry;
use Carbon\Carbon;
use Illuminate\Support\Str;

it('creates a rental inquiry with UUID primary key', function () {
    $inquiry = RentalInquiry::factory()->create();
    expect($inquiry->id)->toBeString();
    expect(Str::isUuid($inquiry->id))->toBeTrue();
});

it('casts event_type to RentalEventType enum', function () {
    $inquiry = RentalInquiry::factory()->create(['event_type' => 'corporate']);
    expect($inquiry->event_type)->toBe(RentalEventType::Corporate);
});

it('casts status to InquiryStatus enum', function () {
    $inquiry = RentalInquiry::factory()->create(['status' => 'contacted']);
    expect($inquiry->status)->toBe(InquiryStatus::Contacted);
});

it('defaults status to pending', function () {
    $inquiry = RentalInquiry::factory()->create();
    expect($inquiry->status)->toBe(InquiryStatus::Pending);
});

it('casts preferred_date to date', function () {
    $inquiry = RentalInquiry::factory()->create(['preferred_date' => '2026-07-01']);
    expect($inquiry->preferred_date)->toBeInstanceOf(Carbon::class);
});
