<?php

namespace Database\Seeders;

use App\Models\FeaturedSlide;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FeaturedSlideSeeder extends Seeder
{
    public function run(): void
    {
        $slides = [
            [
                'headline' => 'Festival Week: Final Cut Selects',
                'sub_headline' => 'Six restored prints, one weekend.',
                'image_url' => 'https://picsum.photos/seed/finalcut-festival/1920/800',
                'cta_label' => 'See the lineup',
                'cta_href' => '/events/final-cut-selects',
                'display_order' => 1,
                'published_at' => Carbon::now()->subDay(),
            ],
            [
                'headline' => 'Now Showing at Both Locations',
                'sub_headline' => 'The full slate — downtown and uptown.',
                'image_url' => 'https://picsum.photos/seed/finalcut-now-showing/1920/800',
                'cta_label' => 'Get tickets',
                'cta_href' => '/movies',
                'display_order' => 2,
                'published_at' => Carbon::now()->subDay(),
            ],
            [
                'headline' => 'Premier Members: Early Access',
                'sub_headline' => 'Book before the public. Your seat, first.',
                'image_url' => 'https://picsum.photos/seed/finalcut-premier/1920/800',
                'cta_label' => 'Book now',
                'cta_href' => '/account/loyalty',
                'display_order' => 3,
                'published_at' => Carbon::now()->subDay(),
            ],
            [
                'headline' => 'Gift Cards for Film Lovers',
                'sub_headline' => null,
                'image_url' => 'https://picsum.photos/seed/finalcut-gift-cards/1920/800',
                'cta_label' => 'Shop gift cards',
                'cta_href' => '/gift-cards',
                'display_order' => 4,
                'published_at' => Carbon::now()->subDay(),
            ],
        ];

        foreach ($slides as $slide) {
            FeaturedSlide::firstOrCreate(
                ['headline' => $slide['headline']],
                $slide,
            );
        }
    }
}
