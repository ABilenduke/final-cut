import type { Meta, StoryObj } from '@storybook/vue3'
import type { Movie } from '~/types/movie'
import MovieCard from './MovieCard.vue'

function mockMovie(overrides: Partial<Movie> = {}): Movie {
  return {
    id: 1,
    slug: 'blade-runner-2099',
    title: 'Blade Runner 2099',
    tagline: 'The future is darker than you think.',
    synopsis: 'In a world ravaged by climate collapse, a new blade runner uncovers a conspiracy that could end all synthetic life.',
    runtime: 148,
    rating: 8.2,
    releaseDate: '2026-06-15',
    genres: [
      { id: 878, name: 'Science Fiction' },
      { id: 53, name: 'Thriller' },
      { id: 18, name: 'Drama' },
    ],
    cast: [],
    posterUrl: 'https://image.tmdb.org/t/p/w500/example-poster.jpg',
    backdropUrl: 'https://image.tmdb.org/t/p/w1280/example-backdrop.jpg',
    trailerKey: 'dQw4w9WgXcQ',
    status: 'now_showing',
    ...overrides,
  }
}

const meta: Meta<typeof MovieCard> = {
  title: 'Movie/MovieCard',
  component: MovieCard,
  argTypes: {
    variant: {
      control: 'select',
      options: ['now-showing', 'coming-soon'],
    },
  },
  decorators: [
    () => ({
      template: '<div style="max-width: 20rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof MovieCard>

export const NowShowing: Story = {
  render: () => ({
    components: { MovieCard },
    setup: () => ({ movie: mockMovie() }),
    template: '<MovieCard :movie="movie" />',
  }),
}

export const ComingSoon: Story = {
  render: () => ({
    components: { MovieCard },
    setup: () => ({
      movie: mockMovie({
        title: 'Dune: Part Three',
        slug: 'dune-part-three',
        status: 'coming_soon',
        releaseDate: '2026-11-20',
        rating: 0,
      }),
    }),
    template: '<MovieCard :movie="movie" variant="coming-soon" />',
  }),
}

export const NoGenres: Story = {
  render: () => ({
    components: { MovieCard },
    setup: () => ({
      movie: mockMovie({ genres: [] }),
    }),
    template: '<MovieCard :movie="movie" />',
  }),
}

export const ManyGenres: Story = {
  render: () => ({
    components: { MovieCard },
    setup: () => ({
      movie: mockMovie({
        genres: [
          { id: 878, name: 'Science Fiction' },
          { id: 53, name: 'Thriller' },
          { id: 18, name: 'Drama' },
          { id: 28, name: 'Action' },
          { id: 9648, name: 'Mystery' },
          { id: 12, name: 'Adventure' },
        ],
      }),
    }),
    template: '<MovieCard :movie="movie" />',
  }),
}

export const NoPoster: Story = {
  render: () => ({
    components: { MovieCard },
    setup: () => ({
      movie: mockMovie({ posterUrl: '' }),
    }),
    template: '<MovieCard :movie="movie" />',
  }),
}
