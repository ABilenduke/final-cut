import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { useClock } from '~/composables/useClock'

/**
 * useClock is a lifecycle-bound composable (onMounted / onUnmounted) so the
 * tests have to exercise it from inside a mounted component. A thin host
 * component reads the ref, exposes it through `defineExpose`, and forwards
 * the string into its template so we can both observe the value directly
 * and verify what the DOM shows.
 */
const ClockHost = defineComponent({
  name: 'ClockHost',
  setup(_, { expose }) {
    const time = useClock()
    expose({ time })
    return () => h('span', { class: 'clock-host' }, time.value)
  },
})

describe('useClock', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    // Pin the wall clock to 09:30:05 local time for deterministic formatting.
    vi.setSystemTime(new Date(2026, 3, 19, 9, 30, 5))
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('formats the current time as HH:MM:SS after mount', async () => {
    const wrapper = await mountSuspended(ClockHost)
    // onMounted ticks synchronously with the pinned clock.
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')
  })

  it('updates every second', async () => {
    const wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')

    vi.advanceTimersByTime(1000)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.clock-host').text()).toBe('09:30:06')

    vi.advanceTimersByTime(2000)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.clock-host').text()).toBe('09:30:08')
  })

  it('clears the interval when the component unmounts', async () => {
    const wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')

    wrapper.unmount()

    // If the interval leaked, advancing time would mutate the time ref
    // behind the unmounted component. With cleanup in place, the ref
    // retains its last value — advancing time changes nothing observable.
    vi.advanceTimersByTime(5000)
    // No pending timer owners means advancing doesn't schedule more work.
    expect(vi.getTimerCount()).toBe(0)
  })

  it('pads single-digit hours, minutes, and seconds to two digits', async () => {
    vi.setSystemTime(new Date(2026, 3, 19, 3, 4, 7))
    const wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('03:04:07')
  })
})
