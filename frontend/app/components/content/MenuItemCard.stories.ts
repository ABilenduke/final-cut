import type { Meta, StoryObj } from '@storybook/vue3'
import type { MenuItem } from '~/types/menu-item'
import MenuItemCard from './MenuItemCard.vue'

function makeItem(overrides: Partial<MenuItem> = {}): MenuItem {
  return {
    id: 'pop-lg',
    name: 'Large Popcorn',
    description: 'Classic buttered popcorn in a large tub. Perfect for sharing.',
    price: 999,
    category: 'popcorn',
    imageUrl: '/images/menu/popcorn-large.jpg',
    allergens: ['dairy'],
    dietary: [],
    available: true,
    ...overrides,
  }
}

const meta: Meta<typeof MenuItemCard> = {
  title: 'Content/MenuItemCard',
  component: MenuItemCard,
  decorators: [
    () => ({
      template: '<div style="max-width: 20rem;"><story /></div>',
    }),
  ],
}

export default meta
type Story = StoryObj<typeof MenuItemCard>

export const Default: Story = {
  render: () => ({
    components: { MenuItemCard },
    setup: () => ({ item: makeItem() }),
    template: '<MenuItemCard :item="item" />',
  }),
}

export const WithDietaryTags: Story = {
  render: () => ({
    components: { MenuItemCard },
    setup: () => ({
      item: makeItem({
        id: 'drink-water',
        name: 'Bottled Water',
        description: 'Premium spring water, 500ml.',
        price: 399,
        category: 'drinks',
        allergens: [],
        dietary: ['vegan', 'gluten_free'],
      }),
    }),
    template: '<MenuItemCard :item="item" />',
  }),
}

export const WithNutWarning: Story = {
  render: () => ({
    components: { MenuItemCard },
    setup: () => ({
      item: makeItem({
        id: 'snack-nuts',
        name: 'Trail Mix',
        description: 'A premium blend of roasted nuts, dried fruit, and dark chocolate.',
        price: 599,
        category: 'snacks',
        allergens: ['nuts', 'dairy', 'soy'],
        dietary: ['gluten_free', 'vegetarian'],
      }),
    }),
    template: '<MenuItemCard :item="item" />',
  }),
}

export const NoImage: Story = {
  render: () => ({
    components: { MenuItemCard },
    setup: () => ({
      item: makeItem({ imageUrl: '' }),
    }),
    template: '<MenuItemCard :item="item" />',
  }),
}
