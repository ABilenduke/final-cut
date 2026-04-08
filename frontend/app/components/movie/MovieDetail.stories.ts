import type { Meta, StoryObj } from '@storybook/vue3'
import type { Movie } from '~/types/movie'
import MovieDetail from './MovieDetail.vue'

function mockMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'blade-runner-2099',
    title: 'Blade Runner 2099',
    tagline: 'The future is darker than you think.',
    synopsis: 'In a world ravaged by climate collapse, a new blade runner uncovers a conspiracy that could end all synthetic life. The investigation leads through the neon-drenched ruins of Los Angeles to the heart of a corporation that holds the key to humanity\'s survival.',
    runtime: 148,
    rating: 8.2,
    releaseDate: '2026-06-15',
    genres: [
      { id: 878, name: 'Science Fiction' },
      { id: 53, name: 'Thriller' },
      { id: 18, name: 'Drama' },
    ],
    cast: [
      { id: 1, name: 'Ana de Armas', character: 'Officer K', profileUrl: 'https://image.tmdb.org/t/p/w185/example-1.jpg' },
      { id: 2, name: 'Oscar Isaac', character: 'Niander Wallace Jr.', profileUrl: 'https://image.tmdb.org/t/p/w185/example-2.jpg' },
      { id: 3, name: 'Zendaya', character: 'Luv', profileUrl: 'https://image.tmdb.org/t/p/w185/example-3.jpg' },
      { id: 4, name: 'Dev Patel', character: 'Sapper Morton', profileUrl: null },
    ],
    posterUrl: 'https://image.tmdb.org/t/p/w500/example-poster.jpg',
    backdropUrl: 'https://image.tmdb.org/t/p/w1280/example-backdrop.jpg',
    trailerKey: 'dQw4w9WgXcQ',
    status: 'now_showing',
    ...overrides,
  }
}

const meta: Meta<typeof MovieDetail> = {
  title: 'Movie/MovieDetail',
  component: MovieDetail,
  decorators: [
    () => ({
      template: '<div style="max-width: 45rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof MovieDetail>

export const Default: Story = {
  render: () => ({
    components: { MovieDetail },
    setup: () => ({ movie: mockMovie() }),
    template: '<MovieDetail :movie="movie" />',
  }),
}

export const NoTrailer: Story = {
  render: () => ({
    components: { MovieDetail },
    setup: () => ({ movie: mockMovie({ trailerKey: null }) }),
    template: '<MovieDetail :movie="movie" />',
  }),
}

export const NoCast: Story = {
  render: () => ({
    components: { MovieDetail },
    setup: () => ({ movie: mockMovie({ cast: [] }) }),
    template: '<MovieDetail :movie="movie" />',
  }),
}

export const Minimal: Story = {
  render: () => ({
    components: { MovieDetail },
    setup: () => ({
      movie: mockMovie({
        tagline: '',
        trailerKey: null,
        cast: [],
        genres: [],
      }),
    }),
    template: '<MovieDetail :movie="movie" />',
  }),
}
