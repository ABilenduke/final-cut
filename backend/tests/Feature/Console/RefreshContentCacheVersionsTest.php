<?php

use App\Observers\FeaturedSlideObserver;
use App\Observers\MenuItemObserver;
use Illuminate\Support\Facades\Cache;

it('bumps both content-cache version keys', function () {
    Cache::put(FeaturedSlideObserver::CACHE_VERSION_KEY, 7);
    Cache::put(MenuItemObserver::CACHE_VERSION_KEY, 3);

    $this->artisan('cache:refresh-content-versions')->assertSuccessful();

    expect((int) Cache::get(FeaturedSlideObserver::CACHE_VERSION_KEY))->toBe(8);
    expect((int) Cache::get(MenuItemObserver::CACHE_VERSION_KEY))->toBe(4);
});

it('seeds version keys from absent → 1 on first run', function () {
    Cache::forget(FeaturedSlideObserver::CACHE_VERSION_KEY);
    Cache::forget(MenuItemObserver::CACHE_VERSION_KEY);

    $this->artisan('cache:refresh-content-versions')->assertSuccessful();

    expect((int) Cache::get(FeaturedSlideObserver::CACHE_VERSION_KEY))->toBe(1);
    expect((int) Cache::get(MenuItemObserver::CACHE_VERSION_KEY))->toBe(1);
});

it('does not touch unrelated cache keys', function () {
    Cache::put('pending_booking:pi_test_123', ['preserved' => true], 60);
    Cache::put('gift_card_idempotency:abc', ['preserved' => true], 60);

    $this->artisan('cache:refresh-content-versions')->assertSuccessful();

    expect(Cache::get('pending_booking:pi_test_123'))->toBe(['preserved' => true]);
    expect(Cache::get('gift_card_idempotency:abc'))->toBe(['preserved' => true]);
});
