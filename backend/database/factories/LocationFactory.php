<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $name = fake()->unique()->city().' Cinema';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            // `fake()->phoneNumber()` occasionally emits formats (e.g. with
            // `x1234` extensions, or digits-before-parens like `+1 (555)…`)
            // that Filament's `->tel()` regex rejects — the rule accepts at
            // most one leading parenthesised group. Pin to a
            // `NNN-NNN-NNNN` format that is unambiguously valid.
            'phone' => fake()->numerify('###-###-####'),
            'email' => fake()->unique()->safeEmail(),
            'street' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'postal_code' => fake()->postcode(),
            'country' => 'US',
            // Test-stable timezone — never depend on UTC defaults for local
            // timezone behaviour. Override per-test if a different zone matters.
            'timezone' => 'America/New_York',
            'latitude' => fake()->latitude(25, 49),
            'longitude' => fake()->longitude(-124, -67),
        ];
    }
}
