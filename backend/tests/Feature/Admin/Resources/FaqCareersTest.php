<?php

use App\Filament\Resources\FaqItemResource;
use App\Filament\Resources\JobOpeningResource;
use App\Models\FaqItem;
use App\Models\JobOpening;
use Database\Seeders\FaqItemSeeder;
use Database\Seeders\JobOpeningSeeder;

use function Pest\Laravel\getJson;

// ── FAQ public API ──────────────────────────────────────────────────────────

test('the faq api groups published items by category in display order', function (): void {
    FaqItem::factory()->create([
        'category' => 'Tickets & Booking', 'question' => 'Q2', 'answer' => 'A2',
        'display_order' => 2, 'published_at' => now()->subDay(),
    ]);
    FaqItem::factory()->create([
        'category' => 'Tickets & Booking', 'question' => 'Q1', 'answer' => 'A1',
        'display_order' => 1, 'published_at' => now()->subDay(),
    ]);
    FaqItem::factory()->create([
        'category' => 'Policies', 'question' => 'Q3', 'answer' => 'A3',
        'display_order' => 3, 'published_at' => now()->subDay(),
    ]);
    FaqItem::factory()->create([
        'category' => 'Policies', 'question' => 'Draft', 'display_order' => 0, 'published_at' => null,
    ]);

    getJson('/api/faq')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.category', 'Tickets & Booking')
        ->assertJsonPath('data.0.items.0.question', 'Q1')
        ->assertJsonPath('data.0.items.1.question', 'Q2')
        ->assertJsonPath('data.1.category', 'Policies')
        ->assertJsonCount(1, 'data.1.items');
});

// ── Careers public API ──────────────────────────────────────────────────────

test('the job openings api returns published openings in display order', function (): void {
    JobOpening::factory()->create([
        'title' => 'Projectionist', 'display_order' => 1, 'published_at' => now()->subDay(),
    ]);
    JobOpening::factory()->create([
        'title' => 'Bar Staff', 'employment_type' => 'Part-time',
        'display_order' => 2, 'published_at' => now()->subDay(),
    ]);
    JobOpening::factory()->create(['title' => 'Draft Role', 'published_at' => null]);

    getJson('/api/job-openings')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.title', 'Projectionist')
        ->assertJsonPath('data.1.type', 'Part-time');
});

test('both caches bust on save', function (): void {
    $faq = FaqItem::factory()->create([
        'category' => 'Policies', 'question' => 'Before?', 'published_at' => now()->subDay(),
    ]);
    $job = JobOpening::factory()->create(['title' => 'Before', 'published_at' => now()->subDay()]);

    getJson('/api/faq')->assertJsonPath('data.0.items.0.question', 'Before?');
    getJson('/api/job-openings')->assertJsonPath('data.0.title', 'Before');

    $faq->update(['question' => 'After?']);
    $job->update(['title' => 'After']);

    getJson('/api/faq')->assertJsonPath('data.0.items.0.question', 'After?');
    getJson('/api/job-openings')->assertJsonPath('data.0.title', 'After');
});

// ── Permissions ─────────────────────────────────────────────────────────────

test('manager can manage both; ops can neither', function (): void {
    $this->actingAsManager();
    expect(FaqItemResource::canViewAny())->toBeTrue();
    expect(JobOpeningResource::canViewAny())->toBeTrue();

    $this->actingAsOps();
    expect(FaqItemResource::canViewAny())->toBeFalse();
    expect(JobOpeningResource::canViewAny())->toBeFalse();
});

// ── Seeder parity ───────────────────────────────────────────────────────────

test('the seeders import the legacy static content as live', function (): void {
    $this->seed(FaqItemSeeder::class);
    $this->seed(JobOpeningSeeder::class);

    expect(FaqItem::count())->toBe(18)
        ->and(JobOpening::count())->toBe(3);

    getJson('/api/faq')->assertOk()->assertJsonCount(5, 'data');
    getJson('/api/job-openings')->assertOk()->assertJsonCount(3, 'data');
});
