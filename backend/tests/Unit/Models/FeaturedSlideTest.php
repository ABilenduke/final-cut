<?php

use App\Models\FeaturedSlide;
use Carbon\Carbon;
use Illuminate\Support\Str;

it('creates a featured slide with UUID primary key', function () {
    $slide = FeaturedSlide::factory()->create();
    expect($slide->id)->toBeString();
    expect(Str::isUuid($slide->id))->toBeTrue();
});

it('factory produces a valid published in-window slide by default', function () {
    $slide = FeaturedSlide::factory()->create();

    expect($slide->headline)->toBeString()->not()->toBeEmpty();
    expect($slide->cta_label)->toBeString()->not()->toBeEmpty();
    expect($slide->cta_href)->toBeString()->not()->toBeEmpty();
    expect($slide->image_url)->toBeString()->not()->toBeEmpty();
    expect($slide->published_at)->toBeInstanceOf(Carbon::class);
    expect($slide->published_at->isPast())->toBeTrue();
});

it('casts starts_at, ends_at, published_at to Carbon instances', function () {
    $slide = FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    expect($slide->published_at)->toBeInstanceOf(Carbon::class);
    expect($slide->starts_at)->toBeInstanceOf(Carbon::class);
    expect($slide->ends_at)->toBeInstanceOf(Carbon::class);
});

it('scopeActive returns published slides within window', function () {
    // published, no time window bounds — should appear
    $alwaysActive = FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'starts_at' => null,
        'ends_at' => null,
    ]);

    // published, within explicit time window — should appear
    $inWindow = FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    expect(FeaturedSlide::active()->pluck('id'))
        ->toContain($alwaysActive->id)
        ->toContain($inWindow->id);
});

it('scopeActive excludes draft slides (null published_at)', function () {
    $draft = FeaturedSlide::factory()->draft()->create();

    expect(FeaturedSlide::active()->pluck('id'))
        ->not()->toContain($draft->id);
});

it('scopeActive excludes scheduled slides (published but starts_at in future)', function () {
    $scheduled = FeaturedSlide::factory()->scheduled()->create();

    expect(FeaturedSlide::active()->pluck('id'))
        ->not()->toContain($scheduled->id);
});

it('scopeActive excludes expired slides (ends_at in past)', function () {
    $expired = FeaturedSlide::factory()->expired()->create();

    expect(FeaturedSlide::active()->pluck('id'))
        ->not()->toContain($expired->id);
});

it('scopeActive excludes slides whose published_at is in the future', function () {
    $futurePublished = FeaturedSlide::factory()->create([
        'published_at' => now()->addHour(),
        'starts_at' => null,
        'ends_at' => null,
    ]);

    expect(FeaturedSlide::active()->pluck('id'))
        ->not()->toContain($futurePublished->id);
});

it('orders by display_order ascending', function () {
    FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'display_order' => 3,
    ]);
    FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'display_order' => 1,
    ]);
    FeaturedSlide::factory()->create([
        'published_at' => now()->subHour(),
        'display_order' => 2,
    ]);

    $orders = FeaturedSlide::active()->orderBy('display_order')->pluck('display_order')->all();

    expect($orders)->toBe([1, 2, 3]);
});
