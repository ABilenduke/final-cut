export interface BlogPost {
  title: string
  slug: string
  excerpt: string
  date: string
  author: string
  imageUrl: string
  body: string
}

export const blogPosts: BlogPost[] = [
  {
    title: 'Summer 2026: What We\'re Excited to Show',
    slug: 'summer-lineup-preview',
    excerpt: 'A preview of the films, special screenings, and events coming to Final Cut this summer.',
    date: '2026-04-05',
    author: 'Final Cut Staff',
    imageUrl: '/images/blog/summer-lineup.jpg',
    body: `This summer brings several films that were made for the theatrical experience. Without spoiling any surprises, expect at least three releases where the sound design alone is worth the ticket price. We'll be running extended showtimes on our largest screen for opening weekends.

Throughout June, we're screening the complete Studio Ghibli catalog in chronological order. One film per week, every Saturday afternoon. These are films that many people have only seen on small screens — seeing them projected in a proper theatre, with the detail and color they were designed for, is a genuinely different experience.

Friday and Saturday nights at 11 PM, we're launching a Late Night series featuring cult classics, midnight movies, and the occasional surprise screening. The lobby bar stays open, the atmosphere gets a little looser, and the film selection gets a lot weirder.

Every Sunday morning through the summer, we're running a family matinee with reduced pricing and a sensory-friendly environment — lights slightly up, sound slightly down, and no previews. Check our calendar for the full schedule, including several open-caption screenings.

Planning a summer event? Our auditoriums are available for private screenings — birthdays, corporate events, proposals. Visit our private screenings page to submit an inquiry.

The best way to know what's coming is to create a free account. Members get early access to special event tickets and notifications when new showtimes are posted. See you this summer.`,
  },
  {
    title: 'Behind the Screens: How We Built Our Auditoriums',
    slug: 'behind-the-screens',
    excerpt: 'A look at the technology and philosophy behind Final Cut\'s auditorium design, from acoustic engineering to seat selection.',
    date: '2026-03-28',
    author: 'Marcus Chen',
    imageUrl: '/images/blog/behind-the-screens.jpg',
    body: `Building a movie theatre sounds straightforward until you start thinking about the details. Every decision — from the rake angle of the seats to the thickness of the curtains — affects how a film looks and sounds. Here's how we approached it.

We worked with acoustic engineers for six months before a single seat was installed. The goal was simple: every seat should sound like the best seat. That meant treating each auditorium as a unique acoustic environment rather than applying a one-size-fits-all template. The walls use a combination of absorptive and diffusive panels arranged in patterns specific to each room's dimensions. The result is sound that's immersive without being overwhelming — you hear the film, not the room.

Laser projection was non-negotiable. Traditional lamp-based projectors lose brightness over time and require frequent calibration. Our laser systems deliver consistent color and brightness from the first screening to the thousandth. Each screen is a Stewart Filmscreen with a gain specifically matched to the projector throw distance and auditorium depth.

We tested over a dozen seat models before choosing. The winners had three things in common: generous width, proper lumbar support, and armrests that don't turn into territorial disputes. Every row has enough legroom that you never have to stand to let someone pass. Premium sections feature powered recliners with integrated cupholders and USB charging. Accessible seating was designed into the floor plan from day one — not added as an afterthought.

A place where the technology disappears and the movie takes over. That's always been the goal.`,
  },
  {
    title: 'Final Cut Grand Opening: A New Chapter in Cinema',
    slug: 'grand-opening-announcement',
    excerpt: 'We\'re thrilled to announce the grand opening of Final Cut, a theatre experience designed for people who love movies as much as we do.',
    date: '2026-03-15',
    author: 'Final Cut Staff',
    imageUrl: '/images/blog/grand-opening.jpg',
    body: `After months of careful preparation, we're proud to open our doors. Final Cut isn't just another movie theatre — it's a space built by film lovers, for film lovers.

From the moment you walk in, you'll notice the difference. Our auditoriums feature premium seating with generous spacing, state-of-the-art Dolby Atmos sound, and laser projection that delivers the picture quality directors intended. Every screen was calibrated by professional colorists. Every speaker was tuned to the specific dimensions of its room.

Forget stale nachos and watered-down sodas. Our menu was developed by a local chef who shares our obsession with quality. House-made popcorn seasoned to order. Craft cocktails and local beer on tap. Real food that you'd actually want to eat — not just tolerate because you're hungry.

Everyone who creates an account during opening week automatically earns 500 bonus loyalty points. That's halfway to a free ticket before you've even sat down. Our Premier members get early access to seat selection, 10% off all food, and a free ticket on their birthday.

We're hosting free community screenings throughout our opening weekend. No tickets required — just show up and experience what cinema should feel like.

Welcome to Final Cut. The show is about to start.`,
  },
]
