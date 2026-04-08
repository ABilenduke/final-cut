import type { Meta, StoryObj } from '@storybook/vue3'
import MovieRatingBadge from './MovieRatingBadge.vue'

const meta: Meta<typeof MovieRatingBadge> = {
  title: 'Movie/MovieRatingBadge',
  component: MovieRatingBadge,
  argTypes: {
    rating: { control: { type: 'range', min: 0, max: 10, step: 0.1 } },
  },
}

export default meta
type Story = StoryObj<typeof MovieRatingBadge>

export const Default: Story = {
  args: { rating: 7.8 },
  render: (args) => ({
    components: { MovieRatingBadge },
    setup: () => ({ args }),
    template: '<MovieRatingBadge v-bind="args" />',
  }),
}

export const HighRating: Story = {
  render: () => ({
    components: { MovieRatingBadge },
    template: '<MovieRatingBadge :rating="10" />',
  }),
}

export const LowRating: Story = {
  render: () => ({
    components: { MovieRatingBadge },
    template: '<MovieRatingBadge :rating="0" />',
  }),
}

export const AllRatings: Story = {
  render: () => ({
    components: { MovieRatingBadge },
    template: `
      <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
        <MovieRatingBadge :rating="0" />
        <MovieRatingBadge :rating="3.2" />
        <MovieRatingBadge :rating="5.5" />
        <MovieRatingBadge :rating="7.8" />
        <MovieRatingBadge :rating="9.1" />
        <MovieRatingBadge :rating="10" />
      </div>
    `,
  }),
}
