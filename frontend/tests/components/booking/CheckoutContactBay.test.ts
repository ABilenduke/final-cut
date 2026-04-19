import { describe, it, expect } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CheckoutContactBay from '~/components/booking/CheckoutContactBay.vue'

const baseProps = {
  fullName: '',
  email: '',
  phone: '',
  reelSocietyId: '',
}

describe('CheckoutContactBay', () => {
  it('renders the §01 numbered header with the contact title', async () => {
    const wrapper = await mountSuspended(CheckoutContactBay, { props: baseProps })
    expect(wrapper.find('.bay__number').text()).toBe('§ 01')
    expect(wrapper.find('.bay__title').text()).toContain('Your')
  })

  it('shows sign-in / guest buttons when not authenticated', async () => {
    const wrapper = await mountSuspended(CheckoutContactBay, { props: baseProps })
    const auth = wrapper.find('.contact-bay__auth')
    expect(auth.exists()).toBe(true)
    expect(auth.text()).toContain('Sign in to Reel Society')
    expect(auth.text()).toContain('Continue as guest')
  })

  it('hides sign-in / guest buttons when authenticated', async () => {
    const wrapper = await mountSuspended(CheckoutContactBay, {
      props: { ...baseProps, isAuthenticated: true },
    })
    expect(wrapper.find('.contact-bay__auth').exists()).toBe(false)
  })

  it('emits sign-in when the sign-in button is clicked', async () => {
    const wrapper = await mountSuspended(CheckoutContactBay, { props: baseProps })
    const signIn = wrapper.findAll('.contact-bay__auth-btn')[0]
    await signIn.trigger('click')
    expect(wrapper.emitted('sign-in')).toBeDefined()
  })

  it('emits update:fullName when the name field changes', async () => {
    const wrapper = await mountSuspended(CheckoutContactBay, { props: baseProps })
    const input = wrapper.findAllComponents({ name: 'CvInput' })[0]
    input.vm.$emit('update:modelValue', 'Jane Appleseed')
    await wrapper.vm.$nextTick()
    const emitted = wrapper.emitted('update:fullName')
    expect(emitted).toBeDefined()
    expect(emitted![0][0]).toBe('Jane Appleseed')
  })
})
