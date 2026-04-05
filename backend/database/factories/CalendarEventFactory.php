<?php

namespace Database\Factories;

use App\Enums\CalendarEventType;
use App\Models\CalendarEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CalendarEvent> */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);
        $date = fake()->dateTimeBetween('now', '+30 days');

        return [
            'type' => fake()->randomElement(CalendarEventType::cases()),
            'title' => $title,
            'date' => $date,
            'start_time' => $date,
            'end_time' => (clone $date)->modify('+2 hours'),
            'description' => fake()->paragraph(),
            'movie_slug' => null,
            'image_url' => null,
            'slug' => Str::slug($title).'-'.fake()->unique()->randomNumber(4),
            'ticket_url' => null,
            'loyalty_only' => false,
            'accessibility_tags' => [],
        ];
    }

    public function specialEvent(): static
    {
        return $this->state(fn () => ['type' => CalendarEventType::SpecialEvent]);
    }

    public function loyaltyExclusive(): static
    {
        return $this->state(fn () => [
            'type' => CalendarEventType::LoyaltyExclusive,
            'loyalty_only' => true,
        ]);
    }
}
