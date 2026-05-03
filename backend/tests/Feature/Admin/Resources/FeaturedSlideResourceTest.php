<?php

use App\Filament\Resources\FeaturedSlideResource;
use App\Filament\Resources\FeaturedSlideResource\Pages\CreateFeaturedSlide;
use App\Filament\Resources\FeaturedSlideResource\Pages\EditFeaturedSlide;
use App\Filament\Resources\FeaturedSlideResource\Pages\ListFeaturedSlides;
use App\Models\FeaturedSlide;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

// ---------------------------------------------------------------------------
// (a) Permission matrix
// ---------------------------------------------------------------------------

test('admin can view, create, edit, and delete featured slides', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->draft()->create();

    expect(FeaturedSlideResource::canViewAny())->toBeTrue()
        ->and(FeaturedSlideResource::canCreate())->toBeTrue()
        ->and(FeaturedSlideResource::canEdit($slide))->toBeTrue()
        ->and(FeaturedSlideResource::canDelete($slide))->toBeTrue();
});

test('manager can view, create, edit, and delete featured slides', function (): void {
    $this->actingAsManager();
    $slide = FeaturedSlide::factory()->draft()->create();

    expect(FeaturedSlideResource::canViewAny())->toBeTrue()
        ->and(FeaturedSlideResource::canCreate())->toBeTrue()
        ->and(FeaturedSlideResource::canEdit($slide))->toBeTrue()
        ->and(FeaturedSlideResource::canDelete($slide))->toBeTrue();
});

test('ops cannot view, create, edit, or delete featured slides', function (): void {
    $this->actingAsOps();
    $slide = FeaturedSlide::factory()->draft()->create();

    expect(FeaturedSlideResource::canViewAny())->toBeFalse()
        ->and(FeaturedSlideResource::canCreate())->toBeFalse()
        ->and(FeaturedSlideResource::canEdit($slide))->toBeFalse()
        ->and(FeaturedSlideResource::canDelete($slide))->toBeFalse();

    $this->get(ListFeaturedSlides::getUrl())->assertForbidden();
});

test('nobody role cannot access featured slides', function (): void {
    $this->actingAsNobody();
    $slide = FeaturedSlide::factory()->draft()->create();

    expect(FeaturedSlideResource::canViewAny())->toBeFalse();

    $this->get(ListFeaturedSlides::getUrl())->assertForbidden();
    $this->get(CreateFeaturedSlide::getUrl())->assertForbidden();
});

test('anonymous user cannot access featured slides list', function (): void {
    $this->get(ListFeaturedSlides::getUrl())->assertRedirect();
});

// ---------------------------------------------------------------------------
// (b) Status badge synthesis
// ---------------------------------------------------------------------------

test('status badge is draft when published_at is null', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->draft()->create();

    expect($slide->displayStatus())->toBe('draft');
});

test('status badge is scheduled when published_at is set and starts_at is in the future', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->scheduled()->create();
    // scheduled() state: published_at in past, starts_at in future

    expect($slide->displayStatus())->toBe('scheduled');
});

test('status badge is scheduled when published_at itself is in the future', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->create([
        'published_at' => now()->addDay(),
        'starts_at' => null,
        'ends_at' => null,
    ]);

    expect($slide->displayStatus())->toBe('scheduled');
});

test('status badge is live for a published slide with no window', function (): void {
    $this->actingAsAdmin();
    // Default factory state: published_at in past, no starts_at/ends_at
    $slide = FeaturedSlide::factory()->create();

    expect($slide->displayStatus())->toBe('live');
});

test('status badge is live for a published slide inside its window', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->create([
        'published_at' => now()->subDay(),
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    expect($slide->displayStatus())->toBe('live');
});

test('status badge is expired when ends_at is in the past', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->expired()->create();

    expect($slide->displayStatus())->toBe('expired');
});

// ---------------------------------------------------------------------------
// (c) "Publish" action sets published_at and writes activity log
// ---------------------------------------------------------------------------

test('publish action sets published_at to a recent timestamp', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->draft()->create();

    $before = now()->subSecond();

    Livewire::test(ListFeaturedSlides::class)
        ->callTableAction('publish', $slide)
        ->assertHasNoTableActionErrors();

    $slide->refresh();
    expect($slide->published_at)->not->toBeNull()
        ->and($slide->published_at->isAfter($before))->toBeTrue();
});

test('publish action writes an activity log entry', function (): void {
    $admin = $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->draft()->create();

    Livewire::test(ListFeaturedSlides::class)
        ->callTableAction('publish', $slide)
        ->assertHasNoTableActionErrors();

    expect(Activity::where('log_name', 'admin')
        ->where('description', 'published')
        ->where('subject_id', $slide->id)
        ->where('causer_id', $admin->id)
        ->count()
    )->toBe(1);
});

test('publish action is hidden for already-published slides', function (): void {
    $this->actingAsAdmin();
    // default factory = published (live)
    $slide = FeaturedSlide::factory()->create();

    Livewire::test(ListFeaturedSlides::class)
        ->assertTableActionHidden('publish', $slide);
});

test('publish action is visible for draft slides', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->draft()->create();

    Livewire::test(ListFeaturedSlides::class)
        ->assertTableActionVisible('publish', $slide);
});

// ---------------------------------------------------------------------------
// (d) Drag-to-reorder persists display_order
// ---------------------------------------------------------------------------

test('reordering slides persists display_order', function (): void {
    $this->actingAsAdmin();

    $first = FeaturedSlide::factory()->create(['display_order' => 0]);
    $second = FeaturedSlide::factory()->create(['display_order' => 1]);

    // Simulate Livewire reorderTable call: swap the order
    Livewire::test(ListFeaturedSlides::class)
        ->call('reorderTable', [$second->id, $first->id]);

    $first->refresh();
    $second->refresh();

    // Filament's default reorderable writes 1-based display_order values
    // (the new position in the list, starting at 1). After swapping,
    // $second is at position 1 and $first is at position 2.
    expect($second->display_order)->toBe(1)
        ->and($first->display_order)->toBe(2);
});

// ---------------------------------------------------------------------------
// (e) Form validation
// ---------------------------------------------------------------------------

test('headline is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.cta_label', 'See films')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasFormErrors(['headline']);
});

test('headline max length is 80 characters', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', str_repeat('a', 81))
        ->set('data.cta_label', 'See films')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasFormErrors(['headline']);
});

test('headline at exactly 80 characters passes max length', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', str_repeat('a', 80))
        ->set('data.cta_label', 'See films')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasNoFormErrors(['headline']);
});

test('sub_headline max length is 160 characters', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Valid Headline')
        ->set('data.sub_headline', str_repeat('b', 161))
        ->set('data.cta_label', 'See films')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasFormErrors(['sub_headline']);
});

test('sub_headline is optional', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Headline Without Sub')
        ->set('data.cta_label', 'Book now')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasNoFormErrors(['sub_headline']);
});

test('cta_label max length is 24 characters', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Test Headline')
        ->set('data.cta_label', str_repeat('c', 25))
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasFormErrors(['cta_label']);
});

test('cta_label is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Test Headline')
        ->set('data.cta_href', '/movies')
        ->call('create')
        ->assertHasFormErrors(['cta_label']);
});

test('cta_href is required', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Test Headline')
        ->set('data.cta_label', 'See films')
        ->call('create')
        ->assertHasFormErrors(['cta_href']);
});

test('admin can create a featured slide and it appears in the list', function (): void {
    $this->actingAsAdmin();

    Livewire::test(CreateFeaturedSlide::class)
        ->set('data.headline', 'Summer Blockbusters')
        ->set('data.sub_headline', 'The biggest films of the season')
        ->set('data.cta_label', 'See lineup')
        ->set('data.cta_href', '/movies')
        ->set('data.display_order', 1)
        ->call('create')
        ->assertHasNoFormErrors();

    $slide = FeaturedSlide::where('headline', 'Summer Blockbusters')->first();
    expect($slide)->not->toBeNull()
        ->and($slide->cta_label)->toBe('See lineup')
        ->and($slide->published_at)->toBeNull(); // default: draft
});

test('admin can edit a featured slide', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->create(['headline' => 'Original Headline']);

    Livewire::test(EditFeaturedSlide::class, ['record' => $slide->getRouteKey()])
        ->set('data.headline', 'Updated Headline')
        ->call('save')
        ->assertHasNoFormErrors();

    $slide->refresh();
    expect($slide->headline)->toBe('Updated Headline');
});

test('admin can delete a featured slide', function (): void {
    $this->actingAsAdmin();
    $slide = FeaturedSlide::factory()->create();

    Livewire::test(EditFeaturedSlide::class, ['record' => $slide->getRouteKey()])
        ->callAction('delete');

    expect(FeaturedSlide::find($slide->id))->toBeNull();
});
