import type { Meta, StoryObj } from '@storybook/vue3'
import CalendarFilters from './CalendarFilters.vue'

const meta: Meta<typeof CalendarFilters> = {
  title: 'Calendar/CalendarFilters',
  component: CalendarFilters,
}

export default meta
type Story = StoryObj<typeof CalendarFilters>

export const Default: Story = {
  render: () => ({
    components: { CalendarFilters },
    data: () => ({
      view: 'month' as const,
      filters: [] as string[],
      a11yFilters: [] as string[],
    }),
    template: `
      <CalendarFilters
        :active-view="view"
        :active-filters="filters"
        :active-accessibility-filters="a11yFilters"
        @view-change="view = $event"
        @filter-change="filters = $event"
        @accessibility-filter-change="a11yFilters = $event"
      />
    `,
  }),
}

export const WithActiveFilters: Story = {
  render: () => ({
    components: { CalendarFilters },
    data: () => ({
      view: 'week' as const,
      filters: ['showtime', 'special_event'],
      a11yFilters: ['sensory_friendly'],
    }),
    template: `
      <CalendarFilters
        :active-view="view"
        :active-filters="filters"
        :active-accessibility-filters="a11yFilters"
        @view-change="view = $event"
        @filter-change="filters = $event"
        @accessibility-filter-change="a11yFilters = $event"
      />
    `,
  }),
}

export const ListView: Story = {
  render: () => ({
    components: { CalendarFilters },
    data: () => ({
      view: 'list' as const,
      filters: [] as string[],
      a11yFilters: [] as string[],
    }),
    template: `
      <CalendarFilters
        :active-view="view"
        :active-filters="filters"
        :active-accessibility-filters="a11yFilters"
        @view-change="view = $event"
        @filter-change="filters = $event"
        @accessibility-filter-change="a11yFilters = $event"
      />
    `,
  }),
}
