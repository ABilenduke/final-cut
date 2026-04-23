<?php

namespace Database\Factories;

use App\Models\Auditorium;
use App\Models\AuditoriumSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditoriumSection> */
class AuditoriumSectionFactory extends Factory
{
    protected $model = AuditoriumSection::class;

    public function definition(): array
    {
        // Sequential `Section N` default; callers that want the canonical
        // Standard/Premium/Accessible triplet use the named states below.
        // Original `fake()->unique()->randomElement([...])` on a 3-element
        // pool would throw OverflowException on the 4th creation.
        return [
            'auditorium_id' => Auditorium::factory(),
            'name' => 'Section '.fake()->unique()->numberBetween(1, 10000),
            'price_multiplier' => 1.00,
            'display_order' => 0,
        ];
    }

    public function standard(): static
    {
        return $this->state(fn () => [
            'name' => 'Standard',
            'price_multiplier' => 1.00,
            'display_order' => 10,
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn () => [
            'name' => 'Premium',
            'price_multiplier' => 1.25,
            'display_order' => 20,
        ]);
    }

    public function accessible(): static
    {
        return $this->state(fn () => [
            'name' => 'Accessible',
            'price_multiplier' => 1.00,
            'display_order' => 30,
        ]);
    }
}
