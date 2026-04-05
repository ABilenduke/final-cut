<?php

namespace Database\Seeders;

use App\Enums\MenuCategory;
use App\Models\Location;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Popcorn
            ['name' => 'Small Popcorn', 'description' => 'Freshly popped buttered popcorn.', 'price' => 599, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => []],
            ['name' => 'Medium Popcorn', 'description' => 'Freshly popped buttered popcorn.', 'price' => 799, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => []],
            ['name' => 'Large Popcorn', 'description' => 'Freshly popped buttered popcorn. Free refill.', 'price' => 999, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => []],
            ['name' => 'Caramel Popcorn', 'description' => 'Sweet caramel-coated popcorn.', 'price' => 899, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy', 'gluten'], 'dietary' => []],

            // Drinks
            ['name' => 'Small Soft Drink', 'description' => 'Choice of Coca-Cola, Sprite, or Fanta.', 'price' => 399, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan']],
            ['name' => 'Large Soft Drink', 'description' => 'Choice of Coca-Cola, Sprite, or Fanta.', 'price' => 549, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan']],
            ['name' => 'Bottled Water', 'description' => 'Still or sparkling.', 'price' => 299, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan', 'gluten_free']],
            ['name' => 'Craft Beer', 'description' => 'Local IPA or lager. Must be 21+.', 'price' => 899, 'category' => MenuCategory::Drinks, 'allergens' => ['gluten'], 'dietary' => ['vegan']],
            ['name' => 'House Wine', 'description' => 'Red or white. Must be 21+.', 'price' => 999, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan', 'gluten_free']],

            // Snacks
            ['name' => 'Nachos with Cheese', 'description' => 'Tortilla chips with warm cheese sauce and jalapeños.', 'price' => 799, 'category' => MenuCategory::Snacks, 'allergens' => ['dairy', 'gluten'], 'dietary' => ['vegetarian']],
            ['name' => 'Soft Pretzel', 'description' => 'Warm salted pretzel with mustard.', 'price' => 599, 'category' => MenuCategory::Snacks, 'allergens' => ['gluten'], 'dietary' => ['vegetarian']],
            ['name' => 'Hot Dog', 'description' => 'Classic beef frank with condiments.', 'price' => 699, 'category' => MenuCategory::Snacks, 'allergens' => ['gluten'], 'dietary' => []],
            ['name' => 'Candy Bar', 'description' => 'Assorted candy bars.', 'price' => 399, 'category' => MenuCategory::Snacks, 'allergens' => ['nuts', 'dairy', 'soy'], 'dietary' => []],
            ['name' => 'Trail Mix', 'description' => 'Nuts, dried fruit, and chocolate chips.', 'price' => 499, 'category' => MenuCategory::Snacks, 'allergens' => ['nuts', 'dairy'], 'dietary' => ['gluten_free']],

            // Combos
            ['name' => 'Date Night Combo', 'description' => 'Large popcorn, 2 large drinks, and a candy bar.', 'price' => 1999, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'nuts'], 'dietary' => []],
            ['name' => 'Family Pack', 'description' => '2 large popcorns, 4 drinks, and 2 candy bars.', 'price' => 2999, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'nuts'], 'dietary' => []],
            ['name' => 'Solo Snacker', 'description' => 'Small popcorn and a small drink.', 'price' => 899, 'category' => MenuCategory::Combos, 'allergens' => ['dairy'], 'dietary' => []],
            ['name' => 'Premium Combo', 'description' => 'Large popcorn, craft beer or wine, and nachos.', 'price' => 2499, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'gluten'], 'dietary' => []],

            // Specials
            ['name' => 'Loaded Fries', 'description' => 'Seasoned fries with cheese, bacon, and sour cream.', 'price' => 899, 'category' => MenuCategory::Specials, 'allergens' => ['dairy', 'gluten'], 'dietary' => []],
            ['name' => 'Churros', 'description' => 'Cinnamon sugar churros with chocolate dipping sauce.', 'price' => 699, 'category' => MenuCategory::Specials, 'allergens' => ['gluten', 'dairy'], 'dietary' => ['vegetarian']],
            ['name' => 'Ice Cream Sundae', 'description' => 'Vanilla ice cream with your choice of toppings.', 'price' => 799, 'category' => MenuCategory::Specials, 'allergens' => ['dairy', 'nuts'], 'dietary' => ['vegetarian', 'gluten_free']],
        ];

        foreach ($items as $item) {
            MenuItem::create($item);
        }

        $this->attachToLocations();
    }

    private function attachToLocations(): void
    {
        $downtown = Location::where('slug', 'downtown')->first();
        $eastside = Location::where('slug', 'eastside')->first();

        if (! $downtown || ! $eastside) {
            $this->command?->warn('MenuItemSeeder: locations not found — skipping pivot attachment. Run AuditoriumSeeder first.');

            return;
        }

        $downtownExclusive = ['Premium Combo'];
        $eastsideExclusive = ['Ice Cream Sundae'];

        $eastsidePriceOverrides = [
            'Small Popcorn'  => 699,
            'Medium Popcorn' => 899,
            'Large Popcorn'  => 1099,
            'Craft Beer'     => 999,
        ];

        $allItems = MenuItem::all();

        foreach ($allItems as $item) {
            $isDowntownExclusive = in_array($item->name, $downtownExclusive);
            $isEastsideExclusive = in_array($item->name, $eastsideExclusive);

            if (! $isEastsideExclusive) {
                $downtown->menuItems()->attach($item->id);
            }

            if (! $isDowntownExclusive) {
                $pivotData = [];
                if (isset($eastsidePriceOverrides[$item->name])) {
                    $pivotData['price_override'] = $eastsidePriceOverrides[$item->name];
                }
                $eastside->menuItems()->attach($item->id, $pivotData);
            }
        }
    }
}
