<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use Illuminate\Database\Seeder;

/**
 * Imports the FAQ content that lived in frontend/app/data/faq.ts before
 * admin-v2 Plan 13 — content parity on `make fresh` (5 categories, 18 items).
 */
class FaqItemSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Tickets & Booking', 'How do I purchase tickets?', 'You can purchase tickets online through our website by selecting a movie, choosing your preferred showtime, picking your seats, and completing the checkout process. You can pay with a credit/debit card or gift card.', 0],
            ['Tickets & Booking', 'Can I get a refund on my tickets?', 'Refunds are available up to 2 hours before the showtime. Please contact our box office or use the booking lookup feature with your confirmation code and email to manage your booking.', 1],
            ['Tickets & Booking', 'How do I find my booking?', 'If you have an account, your bookings appear in your order history. For guest purchases, use the booking lookup page with your confirmation code (e.g., CVF-A3X9K2) and the email address you used at checkout.', 2],
            ['Tickets & Booking', 'Is there a limit to how many tickets I can buy?', 'You can purchase up to 10 tickets per transaction. For larger group bookings, please contact us or check out our private screening packages.', 3],
            ['Tickets & Booking', 'Can I exchange my tickets for a different showtime?', 'Exchanges are available up to 2 hours before your original showtime, subject to availability. Contact our box office for assistance.', 4],
            ['Age Restrictions & Ratings', 'What do movie ratings mean?', 'We follow MPAA ratings: G (General Audiences), PG (Parental Guidance), PG-13 (Parents Strongly Cautioned), R (Restricted — under 17 requires accompanying parent), and NC-17 (No one 17 and under admitted).', 5],
            ['Age Restrictions & Ratings', 'Do I need to show ID for R-rated movies?', 'Yes, guests who appear under 25 may be asked to show valid photo ID for R-rated films. Guests under 17 must be accompanied by a parent or adult guardian.', 6],
            ['Age Restrictions & Ratings', 'Can children attend any movie?', 'Children are welcome at G, PG, and PG-13 films. For R-rated movies, children under 17 must be accompanied by a parent or guardian. NC-17 films are restricted to adults only.', 7],
            ['Accessibility', 'Do you offer assisted listening devices?', 'Yes, assisted listening devices are available free of charge at our box office. Please ask a staff member when you arrive.', 8],
            ['Accessibility', 'Where is wheelchair-accessible seating?', 'Accessible seating is available in every auditorium and can be selected during the online booking process. Look for seats marked with the accessibility icon. Companion seats are located adjacent to accessible positions.', 9],
            ['Accessibility', 'Do you offer open caption or audio described screenings?', 'Yes! Check our What\'s On calendar and filter by accessibility type to find open caption and audio described screenings. We also offer sensory-friendly screenings with adjusted lighting and sound levels.', 10],
            ['Accessibility', 'Is the theater wheelchair accessible?', 'Our entire facility is wheelchair accessible, including ramps at all entrances, accessible restrooms, and elevator access to all levels. Accessible parking is available near the main entrance.', 11],
            ['Food & Allergies', 'Can I pre-order food with my ticket?', 'Yes! During checkout, you can add food and drink items to your order. Your items will be ready for pickup when you arrive.', 12],
            ['Food & Allergies', 'Do you accommodate food allergies?', 'All menu items list their allergens (nuts, dairy, gluten, soy, eggs, shellfish) and dietary tags (vegan, vegetarian, gluten-free). Please check the menu carefully and inform staff of any allergies when picking up your order.', 13],
            ['Food & Allergies', 'Do you have vegan or gluten-free options?', 'Yes, we offer several vegan and gluten-free options across our menu. Look for the dietary tags on each item in our Food & Drink section.', 14],
            ['Policies', 'Can I bring outside food and drinks?', 'Outside food and beverages are not permitted in our auditoriums. We offer a wide variety of snacks, drinks, and meals at our concession stand.', 15],
            ['Policies', 'What is your bag policy?', 'Small bags and purses are welcome. Large bags, backpacks, and coolers may be subject to inspection at entry.', 16],
            ['Policies', 'What happens if I arrive late?', 'You are welcome to enter the auditorium after the film has started, but please be considerate of other guests. Staff can assist you in finding your seats with minimal disruption.', 17],
        ];

        foreach ($rows as [$category, $question, $answer, $order]) {
            FaqItem::firstOrCreate(
                ['category' => $category, 'question' => $question],
                ['answer' => $answer, 'display_order' => $order, 'published_at' => now()],
            );
        }
    }
}
