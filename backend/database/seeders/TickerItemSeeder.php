<?php

namespace Database\Seeders;

use App\Models\TickerItem;
use Illuminate\Database\Seeder;

/**
 * Imports the nine ticker items that lived hardcoded in the frontend's
 * default layout before admin-v2 Plan 11 — content parity on `make fresh`.
 */
class TickerItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['label' => 'Now Showing', 'text' => 'Dune: Part Three · Screen 01 · 7:30'],
            ['label' => 'Event', 'text' => 'Kubrick Retrospective · Fri 9:00 PM'],
            ['label' => 'Members', 'text' => 'Bar opens 60 min before all screenings'],
            ['label' => 'Reel 0047', 'text' => 'Projection: 70mm · Auditorium 03'],
            ['label' => 'Weather', 'text' => 'Clear · 12°C · Valet open'],
            ['label' => 'Arrivals', 'text' => 'The Cold Dawn · Fri'],
            ['label' => 'Late Show', 'text' => 'Mulholland Drive · Sun 11:00 PM'],
            ['label' => 'Food', 'text' => 'Director’s Flight · paired nightly'],
            ['label' => 'Loyalty', 'text' => 'Charter enrolment open through April'],
        ];

        foreach ($items as $order => $item) {
            TickerItem::firstOrCreate(
                ['label' => $item['label'], 'text' => $item['text']],
                ['display_order' => $order, 'published_at' => now()],
            );
        }
    }
}
