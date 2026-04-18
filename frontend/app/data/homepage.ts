// Static content for the Final Cut home page.
// These sections are editorial/content-managed and not backed by an API yet.

export interface RetrospectiveScreening {
  day: string
  title: string
  time: string
  screen: string
}

export interface RetrospectiveProgramme {
  eyebrow: string
  glyph: string
  tag: string
  tagMeta: string
  title: string
  titleEmphasis: string
  copy: string
  cta: string
  screenings: RetrospectiveScreening[]
}

export const retrospectiveProgramme: RetrospectiveProgramme = {
  eyebrow: 'Feature Programme \u00B7 April',
  glyph: 'K',
  tag: 'Retrospective \u00B7 Fri \u2014 Sun',
  tagMeta: '4 Films \u00B7 70mm',
  title: 'Kubrick',
  titleEmphasis: 'in the grain.',
  copy: 'Four films. Three nights. Preserved 70mm prints from the Warner Archives \u2014 with a post-screening conversation moderated by film critic L\u00E9a Ferrand.',
  cta: 'Reserve Series Pass',
  screenings: [
    { day: 'Fri', title: '2001: A Space Odyssey', time: '9:00 PM', screen: 'Screen 01' },
    { day: 'Sat', title: 'Barry Lyndon', time: '7:30 PM', screen: 'Screen 01' },
    { day: 'Sat', title: 'The Shining', time: '11:00 PM', screen: 'Screen 02' },
    { day: 'Sun', title: 'Eyes Wide Shut', time: '8:15 PM', screen: 'Screen 01' },
  ],
}

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
  priceLabel: 'Become a Member \u00B7 $24/mo',
  ctaLabel: 'View all tiers',
  cardTier: 'Charter Member',
  cardNumber: 'No. 0047',
  cardValidThrough: 'Valid through 12 \u00B7 2027',
  cardSociety: 'Reel Society',
  cardTitle: 'Final',
  cardTitleEmphasis: 'Cut.',
  perks: [
    { title: 'Unlimited screenings', detail: 'Every film, every screen, every night.' },
    { title: 'Early booking', detail: 'Seats unlocked 48h before general release.' },
    { title: 'Reserved row', detail: 'Center, Row F \u2014 held for members at all screenings.' },
    { title: 'Director evenings', detail: 'Post-screening conversations, four per year.' },
  ],
}

export interface FoodItem {
  index: string
  name: string
  description: string
  priceLabel: string
  meta: string
}

export const foodItems: FoodItem[] = [
  {
    index: 'No. 01',
    name: 'Brown Butter Popcorn',
    description: 'Locally popped, finished with brown butter, flaky salt, and rosemary. The only popcorn that earns its place in the dark.',
    priceLabel: 'from $6.50',
    meta: 'Small \u00B7 Medium \u00B7 Large',
  },
  {
    index: 'No. 02',
    name: 'The Director\u2019s Flight',
    description: 'Three wines paired to tonight\u2019s feature by our sommelier. Served in the lobby, continued in the auditorium.',
    priceLabel: '$22',
    meta: '2 oz \u00B7 3 pours \u00B7 curated weekly',
  },
  {
    index: 'No. 03',
    name: 'Cold Brew Negroni',
    description: 'House gin, Campari, sweet vermouth, a float of slow-steeped cold brew. Bitter, quiet, perfect for a late screening.',
    priceLabel: '$16',
    meta: 'Rocks \u00B7 Orange peel',
  },
]

// Placeholder showtime slots rendered in the hero side panel when no live showtime data is available.
export interface HeroShowtimeSlot {
  time: string
  meridiem: string
  soldOut?: boolean
}

export const placeholderShowtimeSlots: HeroShowtimeSlot[] = [
  { time: '1:15', meridiem: 'PM \u00B7 Sold', soldOut: true },
  { time: '3:45', meridiem: 'PM' },
  { time: '6:30', meridiem: 'PM' },
  { time: '9:15', meridiem: 'PM' },
  { time: '10:45', meridiem: 'PM' },
  { time: '12:30', meridiem: 'AM \u00B7 70mm' },
  { time: '1:45', meridiem: 'AM \u00B7 Sold', soldOut: true },
  { time: '3:00', meridiem: 'AM' },
]
