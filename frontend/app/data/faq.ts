export interface FaqCategory {
  category: string
  items: Array<{ question: string; answer: string }>
}

export const faqData: FaqCategory[] = [
  {
    category: 'Tickets & Booking',
    items: [
      {
        question: 'How do I purchase tickets?',
        answer: 'You can purchase tickets online through our website by selecting a movie, choosing your preferred showtime, picking your seats, and completing the checkout process. You can pay with a credit/debit card or gift card.',
      },
      {
        question: 'Can I get a refund on my tickets?',
        answer: 'Refunds are available up to 2 hours before the showtime. Please contact our box office or use the booking lookup feature with your confirmation code and email to manage your booking.',
      },
      {
        question: 'How do I find my booking?',
        answer: 'If you have an account, your bookings appear in your order history. For guest purchases, use the booking lookup page with your confirmation code (e.g., CVF-A3X9K2) and the email address you used at checkout.',
      },
      {
        question: 'Is there a limit to how many tickets I can buy?',
        answer: 'You can purchase up to 10 tickets per transaction. For larger group bookings, please contact us or check out our private screening packages.',
      },
      {
        question: 'Can I exchange my tickets for a different showtime?',
        answer: 'Exchanges are available up to 2 hours before your original showtime, subject to availability. Contact our box office for assistance.',
      },
    ],
  },
  {
    category: 'Age Restrictions & Ratings',
    items: [
      {
        question: 'What do movie ratings mean?',
        answer: 'We follow MPAA ratings: G (General Audiences), PG (Parental Guidance), PG-13 (Parents Strongly Cautioned), R (Restricted — under 17 requires accompanying parent), and NC-17 (No one 17 and under admitted).',
      },
      {
        question: 'Do I need to show ID for R-rated movies?',
        answer: 'Yes, guests who appear under 25 may be asked to show valid photo ID for R-rated films. Guests under 17 must be accompanied by a parent or adult guardian.',
      },
      {
        question: 'Can children attend any movie?',
        answer: 'Children are welcome at G, PG, and PG-13 films. For R-rated movies, children under 17 must be accompanied by a parent or guardian. NC-17 films are restricted to adults only.',
      },
    ],
  },
  {
    category: 'Accessibility',
    items: [
      {
        question: 'Do you offer assisted listening devices?',
        answer: 'Yes, assisted listening devices are available free of charge at our box office. Please ask a staff member when you arrive.',
      },
      {
        question: 'Where is wheelchair-accessible seating?',
        answer: 'Accessible seating is available in every auditorium and can be selected during the online booking process. Look for seats marked with the accessibility icon. Companion seats are located adjacent to accessible positions.',
      },
      {
        question: 'Do you offer open caption or audio described screenings?',
        answer: 'Yes! Check our What\'s On calendar and filter by accessibility type to find open caption and audio described screenings. We also offer sensory-friendly screenings with adjusted lighting and sound levels.',
      },
      {
        question: 'Is the theater wheelchair accessible?',
        answer: 'Our entire facility is wheelchair accessible, including ramps at all entrances, accessible restrooms, and elevator access to all levels. Accessible parking is available near the main entrance.',
      },
    ],
  },
  {
    category: 'Food & Allergies',
    items: [
      {
        question: 'Can I pre-order food with my ticket?',
        answer: 'Yes! During checkout, you can add food and drink items to your order. Your items will be ready for pickup when you arrive.',
      },
      {
        question: 'Do you accommodate food allergies?',
        answer: 'All menu items list their allergens (nuts, dairy, gluten, soy, eggs, shellfish) and dietary tags (vegan, vegetarian, gluten-free). Please check the menu carefully and inform staff of any allergies when picking up your order.',
      },
      {
        question: 'Do you have vegan or gluten-free options?',
        answer: 'Yes, we offer several vegan and gluten-free options across our menu. Look for the dietary tags on each item in our Food & Drink section.',
      },
    ],
  },
  {
    category: 'Policies',
    items: [
      {
        question: 'Can I bring outside food and drinks?',
        answer: 'Outside food and beverages are not permitted in our auditoriums. We offer a wide variety of snacks, drinks, and meals at our concession stand.',
      },
      {
        question: 'What is your bag policy?',
        answer: 'Small bags and purses are welcome. Large bags, backpacks, and coolers may be subject to inspection at entry.',
      },
      {
        question: 'What happens if I arrive late?',
        answer: 'You are welcome to enter the auditorium after the film has started, but please be considerate of other guests. Staff can assist you in finding your seats with minimal disruption.',
      },
    ],
  },
]
