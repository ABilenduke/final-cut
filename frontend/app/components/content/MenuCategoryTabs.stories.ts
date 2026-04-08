import type { Meta, StoryObj } from '@storybook/vue3'
import MenuCategoryTabs from './MenuCategoryTabs.vue'

const meta: Meta<typeof MenuCategoryTabs> = {
  title: 'Content/MenuCategoryTabs',
  component: MenuCategoryTabs,
}

export default meta
type Story = StoryObj<typeof MenuCategoryTabs>

export const Default: Story = {
  render: () => ({
    components: { MenuCategoryTabs },
    setup() {
      const active = ref('popcorn')
      function onSelect(cat: string) { active.value = cat }
      return { categories: ['popcorn', 'drinks', 'snacks', 'combos', 'specials'], active, onSelect }
    },
    template: '<MenuCategoryTabs :categories="categories" :active="active" @select="onSelect" />',
  }),
}
