import type { Meta, StoryObj } from '@storybook/vue3'
import MovieHero from './MovieHero.vue'
import type { Movie } from '~/types/movie'

const mockMovie: Movie = {
  id: 1,
  slug: 'blade-runner-2049',
  title: 'Blade Runner 2049',
  tagline: 'The key to the future is finally unearthed.',
  synopsis: 'Officer K, a new blade runner for the LAPD, unearths a long-buried secret that has the potential to plunge what\'s left of society into chaos.',
  runtime: 164,
  rating: 8.0,
  releaseDate: '2017-10-06',
  genres: [
    { id: 878, name: 'Science Fiction' },
    { id: 18, name: 'Drama' },
  ],
  cast: [],
  posterUrl: 'https://image.tmdb.org/t/p/w500/gajva2L0rPYkEWjzgFlBXCAVBE5.jpg',
  backdropUrl: 'https://image.tmdb.org/t/p/w1280/sAtoMqDVhNDQBc3QJL3RF6hlhGq.jpg',
  trailerKey: 'gCcx85zbxz4',
  status: 'now_showing',
}

const meta: Meta<typeof MovieHero> = {
  title: 'Movie/MovieHero',
  component: MovieHero,
  parameters: {
    layout: 'fullscreen',
  },
}

export default meta
type Story = StoryObj<typeof MovieHero>

export const Default: Story = {
  render: () => ({
    components: { MovieHero },
    setup: () => ({ movie: mockMovie }),
    template: '<MovieHero :movie="movie" />',
  }),
}

export const NoBackdrop: Story = {
  render: () => ({
    components: { MovieHero },
    setup: () => ({ movie: { ...mockMovie, backdropUrl: '' } }),
    template: '<MovieHero :movie="movie" />',
  }),
}

export const NoTagline: Story = {
  render: () => ({
    components: { MovieHero },
    setup: () => ({ movie: { ...mockMovie, tagline: '' } }),
    template: '<MovieHero :movie="movie" />',
  }),
}

export const LongTitle: Story = {
  render: () => ({
    components: { MovieHero },
    setup: () => ({
      movie: {
        ...mockMovie,
        title: 'The Incredibly Long and Unbelievably Drawn-Out Title of a Film That Refuses to Be Concise',
        tagline: 'Sometimes a title says more than the film ever could, and this is one of those times.',
      },
    }),
    template: '<MovieHero :movie="movie" />',
  }),
}
