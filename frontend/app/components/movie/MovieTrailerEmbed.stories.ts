import type { Meta, StoryObj } from '@storybook/vue3'
import MovieTrailerEmbed from './MovieTrailerEmbed.vue'

const meta: Meta<typeof MovieTrailerEmbed> = {
  title: 'Movie/MovieTrailerEmbed',
  component: MovieTrailerEmbed,
}

export default meta
type Story = StoryObj<typeof MovieTrailerEmbed>

export const Default: Story = {
  args: { trailerKey: 'dQw4w9WgXcQ', title: 'Test Movie' },
  render: (args) => ({
    components: { MovieTrailerEmbed },
    setup: () => ({ args }),
    template: '<MovieTrailerEmbed v-bind="args" />',
  }),
}
