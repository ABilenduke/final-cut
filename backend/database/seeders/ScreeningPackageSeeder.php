<?php

namespace Database\Seeders;

use App\Models\ScreeningPackage;
use Illuminate\Database\Seeder;

/**
 * Imports the four packages that lived hardcoded in the private-screenings
 * page before admin-v2 Plan 14 — content parity on `make fresh`.
 */
class ScreeningPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['Birthday Party', 'Celebrate with a private screening for up to 30 guests. Perfect for kids and adults alike.', 35000, [
                'Private auditorium for 2 hours',
                'Custom birthday message on screen',
                'Popcorn & drinks for all guests',
                'Dedicated party host',
                'Choice of current or classic film',
            ]],
            ['Corporate Event', 'Team building, product launches, client entertainment, or company milestones.', 75000, [
                'Full auditorium rental',
                'Presentation mode with HDMI input',
                'AV equipment and microphone',
                'Catering packages available',
                'Flexible scheduling including weekdays',
            ]],
            ['Proposal Package', 'Pop the question on the big screen. An unforgettable moment in a one-of-a-kind setting.', 50000, [
                'Private auditorium',
                'Custom on-screen message or video',
                'Champagne and chocolates',
                'Professional photo opportunity',
                'Choice of film or personal media',
            ]],
            ['Custom Event', 'Something else in mind? We can tailor a package to fit your vision.', 40000, [
                'Flexible auditorium options',
                'Custom AV setup',
                'Food & beverage packages',
                'Event coordination support',
                'Pricing based on requirements',
            ]],
        ];

        foreach ($packages as $order => [$name, $description, $price, $features]) {
            ScreeningPackage::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $description,
                    'starting_price' => $price,
                    'features' => $features,
                    'display_order' => $order,
                    'published_at' => now(),
                ],
            );
        }
    }
}
