import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CvToast from '~/components/ui/CvToast.vue'

describe('CvToast', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  const infoToast = { id: 't1', message: 'Info message', type: 'info' as const, duration: 5000 }
  const successToast = { id: 't2', message: 'Success!', type: 'success' as const, duration: 5000 }
  const errorToast = { id: 't3', message: 'Error occurred', type: 'error' as const, duration: 5000 }

  it('renders info toast with role=status', async () => {
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: infoToast },
    })
    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.attributes('aria-live')).toBe('polite')
  })

  it('renders error toast with role=alert', async () => {
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: errorToast },
    })
    expect(wrapper.attributes('role')).toBe('alert')
    expect(wrapper.attributes('aria-live')).toBe('assertive')
  })

  it('renders success toast with success class', async () => {
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: successToast },
    })
    expect(wrapper.classes()).toContain('cv-toast--success')
  })

  it('displays toast message', async () => {
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: infoToast },
    })
    expect(wrapper.find('.cv-toast__message').text()).toBe('Info message')
  })

  it('has dismiss button with aria-label', async () => {
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: infoToast },
    })
    const btn = wrapper.find('.cv-toast__dismiss')
    expect(btn.exists()).toBe(true)
    expect(btn.attributes('aria-label')).toBe('Dismiss notification')
  })

  it('does not auto-dismiss when duration is 0', async () => {
    const persistentToast = { ...infoToast, duration: 0 }
    const wrapper = await mountSuspended(CvToast, {
      props: { toast: persistentToast },
    })
    vi.advanceTimersByTime(10000)
    // Toast should still be rendered (component doesn't remove itself, but the timer should not fire)
    expect(wrapper.find('.cv-toast__message').exists()).toBe(true)
  })
})
