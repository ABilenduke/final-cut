<?php

use App\Observers\FeaturedSlideObserver;
use App\Observers\MenuItemObserver;
use Illuminate\Support\Facades\Cache;

/**
 * Pins the from-absent behaviour of the content-cache observers. The
 * existing FeaturedSlideApi/FoodMenuApi tests cover the normal bump
 * path (key already present); this fills the gap for the very first
 * write against a fresh cache, which is the case Laravel's database
 * cache store gets wrong without an explicit seed.
 */
it('seeds the FeaturedSlide version key on first bump when absent', function () {
    Cache::forget(FeaturedSlideObserver::CACHE_VERSION_KEY);

    FeaturedSlideObserver::bumpVersion();

    expect((int) Cache::get(FeaturedSlideObserver::CACHE_VERSION_KEY))->toBe(1);
});

it('seeds the MenuItem version key on first bump when absent', function () {
    Cache::forget(MenuItemObserver::CACHE_VERSION_KEY);

    MenuItemObserver::bumpVersion();

    expect((int) Cache::get(MenuItemObserver::CACHE_VERSION_KEY))->toBe(1);
});

it('increments past a seeded value', function () {
    Cache::put(FeaturedSlideObserver::CACHE_VERSION_KEY, 9);

    FeaturedSlideObserver::bumpVersion();
    FeaturedSlideObserver::bumpVersion();

    expect((int) Cache::get(FeaturedSlideObserver::CACHE_VERSION_KEY))->toBe(11);
});
