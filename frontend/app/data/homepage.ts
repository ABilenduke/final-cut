// Static content for the Final Cut home page.
// The food ("Provisions for the programme.") and retrospective ("Kubrick in the
// grain.") sections are now API-backed — see HomeFoodDrink.vue / HomeRetrospectiveSplit.vue.
// The membership pitch is admin-editable via /api/site-content/home (admin-v2
// Plan 15); `membershipContent` below stays as the render fallback until the
// first admin save. Only the hero's sample-showtime chips remain static.

export interface MembershipPerk {
  title: string
  detail: string
}

export interface MembershipContent {
  eyebrow: string
  title: string
  titleEmphasis: string
  copy: string
  priceLabel: string
  ctaLabel: string
  cardTier: string
  cardNumber: string
  cardValidThrough: string
  cardSociety: string
  cardTitle: string
  cardTitleEmphasis: string
  perks: MembershipPerk[]
}

export const membershipContent: MembershipContent = {
  eyebrow: 'Membership',
  title: 'Join the',
  titleEmphasis: 'Reel Society.',
  copy: 'A monthly membership for people who want to be in a room with a beam of light and a story. Unlimited screenings, early booking, and a seat at our director conversations.',
  priceLabel: 'Become a Member · $24/mo',
  ctaLabel: 'View all tiers',
  cardTier: 'Charter Member',
  cardNumber: 'No. 0047',
  cardValidThrough: 'Valid through 12 · 2027',
  cardSociety: 'Reel Society',
  cardTitle: 'Final',
  cardTitleEmphasis: 'Cut.',
  perks: [
    { title: 'Unlimited screenings', detail: 'Every film, every screen, every night.' },
    { title: 'Early booking', detail: 'Seats unlocked 48h before general release.' },
    { title: 'Reserved row', detail: 'Center, Row F — held for members at all screenings.' },
    { title: 'Director evenings', detail: 'Post-screening conversations, four per year.' },
  ],
}

// Placeholder showtime slots rendered in the hero side panel when no live showtime data is available.
export interface HeroShowtimeSlot {
  time: string
  meridiem: string
  soldOut?: boolean
}

export const placeholderShowtimeSlots: HeroShowtimeSlot[] = [
  { time: '1:15', meridiem: 'PM · Sold', soldOut: true },
  { time: '3:45', meridiem: 'PM' },
  { time: '6:30', meridiem: 'PM' },
  { time: '9:15', meridiem: 'PM' },
  { time: '10:45', meridiem: 'PM' },
  { time: '12:30', meridiem: 'AM · 70mm' },
  { time: '1:45', meridiem: 'AM · Sold', soldOut: true },
  { time: '3:00', meridiem: 'AM' },
]
