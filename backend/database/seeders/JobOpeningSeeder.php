<?php

namespace Database\Seeders;

use App\Models\JobOpening;
use Illuminate\Database\Seeder;

/**
 * Imports the three openings that lived hardcoded in the careers page
 * before admin-v2 Plan 13 — content parity on `make fresh`.
 */
class JobOpeningSeeder extends Seeder
{
    public function run(): void
    {
        $openings = [
            ['Projectionist', 'Operations', 'Full-time', 'Maintain and operate laser projection and Dolby Atmos sound systems across all auditoriums.'],
            ['Front of House Team Member', 'Guest Services', 'Part-time', 'Welcome guests, assist with ticketing, and ensure a great experience from lobby to seat.'],
            ['Kitchen & Bar Staff', 'Food & Beverage', 'Part-time', 'Prepare and serve our chef-crafted menu items, craft cocktails, and specialty beverages.'],
        ];

        foreach ($openings as $order => [$title, $department, $type, $description]) {
            JobOpening::firstOrCreate(
                ['title' => $title],
                [
                    'department' => $department,
                    'employment_type' => $type,
                    'description' => $description,
                    'display_order' => $order,
                    'published_at' => now(),
                ],
            );
        }
    }
}
