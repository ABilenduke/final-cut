import type { Meta, StoryObj } from '@storybook/vue3'
import type { CastMember } from '~/types/movie'
import MovieCastList from './MovieCastList.vue'

function makeCast(count: number): CastMember[] {
  return Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    name: `Actor ${i + 1}`,
    character: `Character ${i + 1}`,
    profileUrl: `https://image.tmdb.org/t/p/w185/example-${i + 1}.jpg`,
  }))
}

const meta: Meta<typeof MovieCastList> = {
  title: 'Movie/MovieCastList',
  component: MovieCastList,
}

export default meta
type Story = StoryObj<typeof MovieCastList>

export const Default: Story = {
  render: () => ({
    components: { MovieCastList },
    setup: () => ({ cast: makeCast(5) }),
    template: '<MovieCastList :cast="cast" />',
  }),
}

export const Empty: Story = {
  render: () => ({
    components: { MovieCastList },
    template: '<MovieCastList :cast="[]" />',
  }),
}

export const Many: Story = {
  render: () => ({
    components: { MovieCastList },
    setup: () => ({ cast: makeCast(12) }),
    template: '<MovieCastList :cast="cast" />',
  }),
}

export const MissingPhotos: Story = {
  render: () => ({
    components: { MovieCastList },
    setup: () => ({
      cast: [
        { id: 1, name: 'Actor One', character: 'Hero', profileUrl: null },
        { id: 2, name: 'Actor Two', character: 'Villain', profileUrl: 'https://image.tmdb.org/t/p/w185/example.jpg' },
        { id: 3, name: 'Actor Three', character: 'Sidekick', profileUrl: null },
      ],
    }),
    template: '<MovieCastList :cast="cast" />',
  }),
}
