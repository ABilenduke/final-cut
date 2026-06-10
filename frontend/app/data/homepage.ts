// Static content for the Final Cut home page.
// The food ("Provisions for the programme.") and retrospective ("Kubrick in the
// grain.") sections are now API-backed — see HomeFoodDrink.vue / HomeRetrospectiveSplit.vue.
// The membership pitch is admin-editable via /api/site-content/home (admin-v2
// Plan 15); `membershipContent` below stays as the render fallback until the
// first admin save. The hero's showtime chips are live data (Plan 16).

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
