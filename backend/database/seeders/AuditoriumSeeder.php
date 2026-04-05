<?php

namespace Database\Seeders;

use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\Location;
use App\Models\Seat;
use Illuminate\Database\Seeder;

class AuditoriumSeeder extends Seeder
{
    public function run(): void
    {
        $downtown = Location::firstOrCreate(
            ['slug' => 'downtown'],
            ['name' => 'Downtown'],
        );

        $eastside = Location::firstOrCreate(
            ['slug' => 'eastside'],
            ['name' => 'Eastside'],
        );

        $locationLayouts = [
            $downtown->id => [
                ['name' => 'Screen 1', 'rows' => 8, 'seats_per_row' => 10, 'premium_rows' => ['D', 'E', 'F']],
                ['name' => 'Screen 2', 'rows' => 12, 'seats_per_row' => 14, 'premium_rows' => ['E', 'F', 'G', 'H']],
                ['name' => 'IMAX', 'rows' => 15, 'seats_per_row' => 20, 'premium_rows' => ['F', 'G', 'H', 'I', 'J']],
            ],
            $eastside->id => [
                ['name' => 'Screen 1', 'rows' => 8, 'seats_per_row' => 10, 'premium_rows' => ['D', 'E', 'F']],
                ['name' => 'Screen 2', 'rows' => 10, 'seats_per_row' => 12, 'premium_rows' => ['D', 'E', 'F']],
            ],
        ];

        foreach ($locationLayouts as $locationId => $layouts) {
            foreach ($layouts as $layout) {
                $totalSeats = $layout['rows'] * $layout['seats_per_row'];
                $auditorium = Auditorium::create([
                    'location_id' => $locationId,
                    'name' => $layout['name'],
                    'total_seats' => $totalSeats,
                ]);

            $lastRowLetter = chr(ord('A') + $layout['rows'] - 1);

            for ($r = 0; $r < $layout['rows']; $r++) {
                $rowLetter = chr(ord('A') + $r);

                for ($s = 1; $s <= $layout['seats_per_row']; $s++) {
                    $type = SeatType::Standard;

                    if ($rowLetter === $lastRowLetter) {
                        // Last row: accessible (aisle seats only — seats 1, 2, last-1, last)
                        if ($s <= 2 || $s >= $layout['seats_per_row'] - 1) {
                            $type = SeatType::Accessible;
                        }
                    } elseif (in_array($rowLetter, $layout['premium_rows'])) {
                        $type = SeatType::Premium;
                    }

                    Seat::create([
                        'auditorium_id' => $auditorium->id,
                        'label' => $rowLetter . $s,
                        'row' => $rowLetter,
                        'number' => $s,
                        'type' => $type,
                    ]);
                }
            }
            }
        }
    }
}
