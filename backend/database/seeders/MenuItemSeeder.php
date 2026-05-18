<?php

namespace Database\Seeders;

use App\Enums\MenuCategory;
use App\Models\Location;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    /**
     * CDN-relative folder for concession hero photos. API resources pass these
     * through AssetUrl::resolve(), so the final host/prefix comes from the
     * configured public disk rather than from seeded data.
     */
    private const CONCESSION_IMAGE_PATH = 'concessions/';

    public function run(): void
    {
        $items = [
            // Popcorn
            ['name' => 'Small Popcorn', 'description' => 'Freshly popped buttered popcorn.', 'price' => 599, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'popcorn_sm.webp'],
            ['name' => 'Medium Popcorn', 'description' => 'Freshly popped buttered popcorn.', 'price' => 799, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'popcorn_md.webp'],
            ['name' => 'Large Popcorn', 'description' => 'Freshly popped buttered popcorn. Free refill.', 'price' => 999, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'popcorn_lg.webp'],
            ['name' => 'Caramel Popcorn', 'description' => 'Sweet caramel-coated popcorn.', 'price' => 899, 'category' => MenuCategory::Popcorn, 'allergens' => ['dairy', 'gluten'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'caramelcorn.webp'],

            // Drinks
            ['name' => 'Small Soft Drink', 'description' => 'Choice of Coca-Cola, Sprite, or Fanta.', 'price' => 399, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan'], 'image_url' => self::CONCESSION_IMAGE_PATH.'soda_sm.webp'],
            ['name' => 'Large Soft Drink', 'description' => 'Choice of Coca-Cola, Sprite, or Fanta.', 'price' => 549, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan'], 'image_url' => self::CONCESSION_IMAGE_PATH.'soda_lg.webp'],
            ['name' => 'Bottled Water', 'description' => 'Still or sparkling.', 'price' => 299, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan', 'gluten_free'], 'image_url' => self::CONCESSION_IMAGE_PATH.'bottle_of_water.webp'],
            ['name' => 'Craft Beer', 'description' => 'Local IPA or lager. Must be 21+.', 'price' => 899, 'category' => MenuCategory::Drinks, 'allergens' => ['gluten'], 'dietary' => ['vegan'], 'image_url' => self::CONCESSION_IMAGE_PATH.'craft_beer.webp'],
            ['name' => 'House Wine', 'description' => 'Red or white. Must be 21+.', 'price' => 999, 'category' => MenuCategory::Drinks, 'allergens' => [], 'dietary' => ['vegan', 'gluten_free'], 'image_url' => self::CONCESSION_IMAGE_PATH.'wine.webp'],

            // Snacks
            ['name' => 'Nachos with Cheese', 'description' => 'Tortilla chips with warm cheese sauce and jalapeños.', 'price' => 799, 'category' => MenuCategory::Snacks, 'allergens' => ['dairy', 'gluten'], 'dietary' => ['vegetarian'], 'image_url' => self::CONCESSION_IMAGE_PATH.'nachos_and_cheese.webp'],
            ['name' => 'Soft Pretzel', 'description' => 'Warm salted pretzel with mustard.', 'price' => 599, 'category' => MenuCategory::Snacks, 'allergens' => ['gluten'], 'dietary' => ['vegetarian'], 'image_url' => self::CONCESSION_IMAGE_PATH.'pretzel.webp'],
            ['name' => 'Hot Dog', 'description' => 'Classic beef frank with condiments.', 'price' => 699, 'category' => MenuCategory::Snacks, 'allergens' => ['gluten'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'hotdog.webp'],
            ['name' => 'Candy Bar', 'description' => 'Assorted candy bars.', 'price' => 399, 'category' => MenuCategory::Snacks, 'allergens' => ['nuts', 'dairy', 'soy'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'candy_bar.webp'],
            ['name' => 'Trail Mix', 'description' => 'Nuts, dried fruit, and chocolate chips.', 'price' => 499, 'category' => MenuCategory::Snacks, 'allergens' => ['nuts', 'dairy'], 'dietary' => ['gluten_free'], 'image_url' => self::CONCESSION_IMAGE_PATH.'trail_mix.webp'],

            // Combos
            ['name' => 'Date Night Combo', 'description' => 'Large popcorn, 2 large drinks, and a candy bar.', 'price' => 1999, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'nuts'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'combo_date_night.webp'],
            ['name' => 'Family Pack', 'description' => '2 large popcorns, 4 drinks, and 2 candy bars.', 'price' => 2999, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'nuts'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'combo_family.webp'],
            ['name' => 'Solo Snacker', 'description' => 'Small popcorn and a small drink.', 'price' => 899, 'category' => MenuCategory::Combos, 'allergens' => ['dairy'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'combo_solo_snacker.webp'],
            ['name' => 'Premium Combo', 'description' => 'Large popcorn, craft beer or wine, and nachos.', 'price' => 2499, 'category' => MenuCategory::Combos, 'allergens' => ['dairy', 'gluten'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'combo_premium.webp'],

            // Specials
            ['name' => 'Loaded Fries', 'description' => 'Seasoned fries with cheese, bacon, and sour cream.', 'price' => 899, 'category' => MenuCategory::Specials, 'allergens' => ['dairy', 'gluten'], 'dietary' => [], 'image_url' => self::CONCESSION_IMAGE_PATH.'loaded_fries.webp'],
            ['name' => 'Churros', 'description' => 'Cinnamon sugar churros with chocolate dipping sauce.', 'price' => 699, 'category' => MenuCategory::Specials, 'allergens' => ['gluten', 'dairy'], 'dietary' => ['vegetarian'], 'image_url' => self::CONCESSION_IMAGE_PATH.'churros.webp'],
            ['name' => 'Ice Cream Sundae', 'description' => 'Vanilla ice cream with your choice of toppings.', 'price' => 799, 'category' => MenuCategory::Specials, 'allergens' => ['dairy', 'nuts'], 'dietary' => ['vegetarian', 'gluten_free'], 'image_url' => self::CONCESSION_IMAGE_PATH.'ice_cream_sundae.webp'],
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
            'Small Popcorn' => 699,
            'Medium Popcorn' => 899,
            'Large Popcorn' => 1099,
            'Craft Beer' => 999,
        ];

        $allItems = MenuItem::all();

        $downtownItems = [];
        $eastsideItems = [];

        foreach ($allItems as $item) {
            $isDowntownExclusive = in_array($item->name, $downtownExclusive);
            $isEastsideExclusive = in_array($item->name, $eastsideExclusive);

            if (! $isEastsideExclusive) {
                $downtownItems[$item->id] = [];
            }

            if (! $isDowntownExclusive) {
                $pivotData = [];
                if (isset($eastsidePriceOverrides[$item->name])) {
                    $pivotData['price_override'] = $eastsidePriceOverrides[$item->name];
                }
                $eastsideItems[$item->id] = $pivotData;
            }
        }

        $downtown->menuItems()->syncWithoutDetaching($downtownItems);
        $eastside->menuItems()->syncWithoutDetaching($eastsideItems);
    }
}
