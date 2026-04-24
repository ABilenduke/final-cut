<?php

namespace Database\Seeders;

use App\Enums\SeatType;
use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use App\Models\Location;
use App\Models\Seat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuditoriumSeeder extends Seeder
{
    public function run(): void
    {
        $downtown = Location::updateOrCreate(
            ['slug' => 'downtown'],
            [
                'name' => 'Downtown',
                'phone' => '(212) 555-0101',
                'email' => 'downtown@finalcut.test',
                'street' => '123 Main Street',
                'city' => 'Downtown',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'US',
                'timezone' => 'America/New_York',
                'latitude' => 40.712776,
                'longitude' => -74.005974,
            ],
        );

        $eastside = Location::updateOrCreate(
            ['slug' => 'eastside'],
            [
                'name' => 'Eastside',
                'phone' => '(212) 555-0202',
                'email' => 'eastside@finalcut.test',
                'street' => '456 East Avenue',
                'city' => 'Eastside',
                'state' => 'NY',
                'postal_code' => '10002',
                'country' => 'US',
                'timezone' => 'America/New_York',
                'latitude' => 40.730610,
                'longitude' => -73.935242,
            ],
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
                    'slug' => Str::slug($layout['name']),
                    'cleanup_minutes' => 20,
                    'total_seats' => $totalSeats,
                ]);

                $sections = [
                    SeatType::Standard->value => AuditoriumSection::create([
                        'auditorium_id' => $auditorium->id,
                        'name' => 'Standard',
                        'price_multiplier' => 1.00,
                        'display_order' => 10,
                    ]),
                    SeatType::Premium->value => AuditoriumSection::create([
                        'auditorium_id' => $auditorium->id,
                        'name' => 'Premium',
                        'price_multiplier' => 1.25,
                        'display_order' => 20,
                    ]),
                    SeatType::Accessible->value => AuditoriumSection::create([
                        'auditorium_id' => $auditorium->id,
                        'name' => 'Accessible',
                        'price_multiplier' => 1.00,
                        'display_order' => 30,
                    ]),
                ];

                $lastRowLetter = chr(ord('A') + $layout['rows'] - 1);
                $now = now();
                $seatRows = [];

                for ($r = 0; $r < $layout['rows']; $r++) {
                    $rowLetter = chr(ord('A') + $r);

                    for ($s = 1; $s <= $layout['seats_per_row']; $s++) {
                        $type = SeatType::Standard;

                        if ($rowLetter === $lastRowLetter) {
                            // Last row aisle seats are accessible (seats 1, 2, last-1, last).
                            if ($s <= 2 || $s >= $layout['seats_per_row'] - 1) {
                                $type = SeatType::Accessible;
                            }
                        } elseif (in_array($rowLetter, $layout['premium_rows'])) {
                            $type = SeatType::Premium;
                        }

                        $seatRows[] = [
                            'id' => (string) Str::uuid(),
                            'auditorium_id' => $auditorium->id,
                            'section_id' => $sections[$type->value]->id,
                            'label' => $rowLetter.$s,
                            'row' => $rowLetter,
                            'number' => $s,
                            'type' => $type->value,
                            'unavailable_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                Seat::insert($seatRows);
            }
        }
    }
}
