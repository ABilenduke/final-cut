<?php

use App\Filament\Resources\ShowtimeResource\Pages\ListShowtimes;
use App\Models\Showtime;
use Livewire\Livewire;

test('admin can bulk-update the seat-tier prices of selected showtimes', function (): void {
    $this->actingAsAdmin();
    $a = Showtime::factory()->create(['price_standard' => 1200, 'price_premium' => 1800, 'price_accessible' => 1000]);
    $b = Showtime::factory()->create(['price_standard' => 1200, 'price_premium' => 1800, 'price_accessible' => 1000]);

    Livewire::test(ListShowtimes::class)
        ->mountTableBulkAction('bulk_update_pricing', [$a, $b])
        ->set('mountedActions.0.data.price_standard', 1500)
        ->set('mountedActions.0.data.price_premium', 2200)
        ->set('mountedActions.0.data.price_accessible', 1300)
        ->callMountedTableBulkAction()
        ->assertHasNoTableBulkActionErrors();

    foreach ([$a, $b] as $showtime) {
        $showtime->refresh();
        expect($showtime->price_standard)->toBe(1500)
            ->and($showtime->price_premium)->toBe(2200)
            ->and($showtime->price_accessible)->toBe(1300);
    }
});

test('bulk pricing requires every tier and changes nothing without them', function (): void {
    $this->actingAsAdmin();
    $showtime = Showtime::factory()->create(['price_standard' => 1200]);

    Livewire::test(ListShowtimes::class)
        ->mountTableBulkAction('bulk_update_pricing', [$showtime])
        ->set('mountedActions.0.data.price_standard', '')
        ->set('mountedActions.0.data.price_premium', 2000)
        ->set('mountedActions.0.data.price_accessible', 900)
        ->callMountedTableBulkAction()
        ->assertHasTableBulkActionErrors(['price_standard']);

    expect($showtime->refresh()->price_standard)->toBe(1200);
});

test('the bulk pricing action is visible to admin but hidden for ops', function (): void {
    $this->actingAsAdmin();
    Livewire::test(ListShowtimes::class)->assertTableBulkActionVisible('bulk_update_pricing');

    $this->actingAsOps();
    Livewire::test(ListShowtimes::class)->assertTableBulkActionHidden('bulk_update_pricing');
});
