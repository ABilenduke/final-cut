import type { Meta, StoryObj } from '@storybook/vue3'
import HomeFeaturedHero from './HomeFeaturedHero.vue'
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

const meta: Meta<typeof HomeFeaturedHero> = {
  title: 'Home/HomeFeaturedHero',
  component: HomeFeaturedHero,
  parameters: {
    layout: 'fullscreen',
  },
}

export default meta
type Story = StoryObj<typeof HomeFeaturedHero>

export const Default: Story = {
  render: () => ({
    components: { HomeFeaturedHero },
    setup: () => ({ movie: mockMovie }),
    template: '<HomeFeaturedHero :movie="movie" />',
  }),
}

export const NoBackdrop: Story = {
  render: () => ({
    components: { HomeFeaturedHero },
    setup: () => ({
      movie: { ...mockMovie, backdropUrl: '' },
    }),
    template: '<HomeFeaturedHero :movie="movie" />',
  }),
}

export const NoTagline: Story = {
  render: () => ({
    components: { HomeFeaturedHero },
    setup: () => ({
      movie: { ...mockMovie, tagline: '' },
    }),
    template: '<HomeFeaturedHero :movie="movie" />',
  }),
}
