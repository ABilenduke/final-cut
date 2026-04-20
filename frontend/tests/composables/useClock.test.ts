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
  // useClock now ref-counts a shared setInterval so multiple consumers
  // share a single ticker. Each test must fully unmount its wrapper in
  // afterEach so the subscriber count returns to zero and the next test
  // starts with a fresh interval under fake timers.
  let wrapper: Awaited<ReturnType<typeof mountSuspended>> | null = null

  beforeEach(() => {
    vi.useFakeTimers()
    // Pin the wall clock to 09:30:05 local time for deterministic formatting.
    vi.setSystemTime(new Date(2026, 3, 19, 9, 30, 5))
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = null
    vi.useRealTimers()
  })

  it('formats the current time as HH:MM:SS after mount', async () => {
    wrapper = await mountSuspended(ClockHost)
    // onMounted ticks synchronously with the pinned clock.
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')
  })

  it('updates every second', async () => {
    wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')

    vi.advanceTimersByTime(1000)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.clock-host').text()).toBe('09:30:06')

    vi.advanceTimersByTime(2000)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.clock-host').text()).toBe('09:30:08')
  })

  it('clears the interval when the last subscriber unmounts', async () => {
    wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('09:30:05')

    wrapper.unmount()
    wrapper = null

    // With cleanup in place, advancing time schedules no more work.
    vi.advanceTimersByTime(5000)
    expect(vi.getTimerCount()).toBe(0)
  })

  it('shares a single interval across multiple consumers', async () => {
    const first = await mountSuspended(ClockHost)
    const second = await mountSuspended(ClockHost)
    // Exactly one ticker is live regardless of how many components
    // subscribed — not two.
    expect(vi.getTimerCount()).toBe(1)

    // Both consumers reflect the same shared state when it advances.
    vi.advanceTimersByTime(1000)
    await first.vm.$nextTick()
    await second.vm.$nextTick()
    expect(first.find('.clock-host').text()).toBe('09:30:06')
    expect(second.find('.clock-host').text()).toBe('09:30:06')

    // Clean up manually; the afterEach hook only tracks a single
    // wrapper reference.
    first.unmount()
    second.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })

  it('pads single-digit hours, minutes, and seconds to two digits', async () => {
    vi.setSystemTime(new Date(2026, 3, 19, 3, 4, 7))
    wrapper = await mountSuspended(ClockHost)
    expect(wrapper.find('.clock-host').text()).toBe('03:04:07')
  })
})
