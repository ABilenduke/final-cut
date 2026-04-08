import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import LoyaltyPointsCard from '~/components/account/LoyaltyPointsCard.vue'

describe('LoyaltyPointsCard', () => {
  it('renders points count', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 1250, tier: 'member', premierExpiry: null },
    })
    const pointsValue = wrapper.find('.loyalty-card__points-value')
    expect(pointsValue.text()).toBe('1,250')
  })

  it('shows member badge for member tier', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 100, tier: 'member', premierExpiry: null },
    })
    const badge = wrapper.find('.loyalty-card__header')
    expect(badge.text()).toBe('Member')
  })

  it('shows accent badge for premier tier', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 500, tier: 'premier', premierExpiry: '2027-01-01' },
    })
    const badge = wrapper.find('.loyalty-card__header')
    expect(badge.text()).toBe('Premier')
  })

  it('shows premier perks when premier', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 500, tier: 'premier', premierExpiry: '2027-01-01' },
    })
    expect(wrapper.find('.loyalty-card__premier').exists()).toBe(true)
    expect(wrapper.text()).toContain('10% food discount')
    expect(wrapper.text()).toContain('Birthday ticket')
    expect(wrapper.text()).toContain('Early seat access')
  })

  it('shows upgrade text for member tier', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 100, tier: 'member', premierExpiry: null },
    })
    expect(wrapper.find('.loyalty-card__upgrade').exists()).toBe(true)
    expect(wrapper.text()).toContain('Upgrade to Premier')
  })

  it('shows expiry date for premier', async () => {
    const wrapper = await mountSuspended(LoyaltyPointsCard, {
      props: { points: 500, tier: 'premier', premierExpiry: '2027-01-01' },
    })
    const expiry = wrapper.find('.loyalty-card__expiry')
    expect(expiry.exists()).toBe(true)
    expect(expiry.text()).toContain('Expires')
  })
})
