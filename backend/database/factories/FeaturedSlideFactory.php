<?php

namespace Database\Factories;

use App\Models\FeaturedSlide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<FeaturedSlide> */
class FeaturedSlideFactory extends Factory
{
    protected $model = FeaturedSlide::class;

    /**
     * Default state: a published, in-window slide (immediately visible publicly).
     * published_at is in the past; no time-window bounds.
     */
    public function definition(): array
    {
        $headline = Str::limit(fake()->sentence(fake()->numberBetween(3, 6)), 78, '');
        $subHeadline = fake()->boolean(70)
            ? Str::limit(fake()->sentence(fake()->numberBetween(5, 12)), 158, '')
            : null;

        return [
            'headline' => $headline,
            'sub_headline' => $subHeadline,
            'image_url' => 'https://via.placeholder.com/1920x800?text='.urlencode(fake()->words(2, true)),
            'cta_label' => fake()->randomElement(['See the lineup', 'Book now', 'Learn more', 'Get tickets', 'Explore']),
            'cta_href' => '/movies',
            'display_order' => fake()->numberBetween(0, 10),
            'starts_at' => null,
            'ends_at' => null,
            'published_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ];
    }

    /**
     * Draft state: published_at is null — the slide is never shown publicly.
     */
    public function draft(): static
    {
        return $this->state(fn () => [
            'published_at' => null,
        ]);
    }

    /**
     * Scheduled state: published, but starts_at is in the future so the display
     * window has not yet opened. scopeActive must exclude these.
     */
    public function scheduled(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->subHour(),
            'starts_at' => now()->addDays(fake()->numberBetween(1, 14)),
            'ends_at' => now()->addDays(fake()->numberBetween(15, 30)),
        ]);
    }

    /**
     * Expired state: published, but ends_at is in the past so the display
     * window has closed. scopeActive must exclude these.
     */
    public function expired(): static
    {
        return $this->state(fn () => [
            'published_at' => now()->subDays(30),
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }
}
