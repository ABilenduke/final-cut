import { describe, it, expect, beforeEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import BridgeFilterRibbon from '~/components/calendar/BridgeFilterRibbon.vue'
import { useBridgeFilters, ALL_CHIPS } from '~/composables/useBridgeFilters'

describe('BridgeFilterRibbon', () => {
  beforeEach(() => {
    const { setChips } = useBridgeFilters()
    setChips(ALL_CHIPS)
  })

  it('renders one chip per non-rental category', async () => {
    const wrapper = await mountSuspended(BridgeFilterRibbon)
    expect(wrapper.findAll('.cv-chip')).toHaveLength(6)
  })

  it('all chips start active when defaults are in effect', async () => {
    const wrapper = await mountSuspended(BridgeFilterRibbon)
    const activeChips = wrapper.findAll('.cv-chip--active')
    expect(activeChips).toHaveLength(6)
  })

  it('toggling a chip flips its active state and the composable agrees', async () => {
    const wrapper = await mountSuspended(BridgeFilterRibbon)
    const chips = wrapper.findAll('.cv-chip')
    const memberChip = chips.find((c) => c.text().includes('Members'))!
    expect(memberChip.classes()).toContain('cv-chip--active')

    await memberChip.trigger('click')

    const { isActive } = useBridgeFilters()
    expect(isActive('member')).toBe(false)
  })

  it('emits change when a chip toggles', async () => {
    const wrapper = await mountSuspended(BridgeFilterRibbon)
    const chip = wrapper.findAll('.cv-chip')[0]!
    await chip.trigger('click')
    expect(wrapper.emitted('change')).toHaveLength(1)
  })

  it('renders the type-color legend with three entries', async () => {
    const wrapper = await mountSuspended(BridgeFilterRibbon)
    expect(wrapper.findAll('.bridge-filter-ribbon__legend-item')).toHaveLength(3)
  })
})
