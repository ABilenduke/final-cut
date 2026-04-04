<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\MenuItem;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@finalcut.test')->firstOrFail();
        $showtimes = Showtime::with('auditorium.seats')->get();
        $menuItems = MenuItem::all();

        // Pick 5 showtimes for bookings
        $selectedShowtimes = $showtimes->random(min(5, $showtimes->count()));

        foreach ($selectedShowtimes as $index => $showtime) {
            $seats = $showtime->auditorium->seats
                ->where('type', \App\Enums\SeatType::Standard)
                ->take(fake()->numberBetween(2, 4));

            $seatTotal = $seats->count() * $showtime->price_standard;
            $foodTotal = 0;

            $booking = Booking::create([
                'showtime_id' => $showtime->id,
                'user_id' => $user->id,
                'status' => BookingStatus::Confirmed,
                'subtotal' => $seatTotal,
                'discount' => 0,
                'total' => $seatTotal,
                'payment_method' => PaymentMethod::Card,
                'stripe_payment_intent_id' => 'pi_seed_' . fake()->regexify('[a-zA-Z0-9]{20}'),
            ]);

            foreach ($seats as $seat) {
                BookingSeat::create([
                    'booking_id' => $booking->id,
                    'showtime_id' => $showtime->id,
                    'seat_id' => $seat->id,
                    'section' => ucfirst($seat->type->value),
                    'price' => $showtime->price_standard,
                ]);
            }

            // Add food items to the first 3 bookings
            if ($index < 3 && $menuItems->isNotEmpty()) {
                $selectedItems = $menuItems->random(min(2, $menuItems->count()));
                foreach ($selectedItems as $menuItem) {
                    $qty = fake()->numberBetween(1, 2);
                    $itemTotal = $menuItem->price * $qty;
                    $foodTotal += $itemTotal;

                    BookingFoodItem::create([
                        'booking_id' => $booking->id,
                        'menu_item_id' => $menuItem->id,
                        'name' => $menuItem->name,
                        'quantity' => $qty,
                        'unit_price' => $menuItem->price,
                        'total_price' => $itemTotal,
                    ]);
                }

                // Update booking total with food
                $booking->update([
                    'subtotal' => $seatTotal + $foodTotal,
                    'total' => $seatTotal + $foodTotal,
                ]);
            }
        }
    }
}
