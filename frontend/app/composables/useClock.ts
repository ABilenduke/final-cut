/**
 * Live wall-clock time as a reactive HH:MM:SS string.
 *
 * SSR renders a static placeholder so hydration does not mismatch; the
 * client starts ticking in onMounted and cleans up in onUnmounted.
 *
 * Shared singleton: the tick state lives in `useState` so every consumer
 * reads the same ref, and the setInterval is reference-counted so we
 * have at most one ticker running regardless of how many components
 * call `useClock()` concurrently. The interval is torn down when the
 * last subscriber unmounts.
 */
let clockIntervalId: ReturnType<typeof setInterval> | null = null
let clockSubscribers = 0

const pad = (n: number) => String(n).padStart(2, '0')

export function useClock() {
  const time = useState<string>('clock-time', () => '--:--:--')

  if (import.meta.client) {
    const tick = () => {
      const d = new Date()
      time.value = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    }

    onMounted(() => {
      clockSubscribers += 1
      tick()
      if (clockIntervalId === null) {
        clockIntervalId = setInterval(tick, 1000)
      }
    })

    onUnmounted(() => {
      clockSubscribers = Math.max(0, clockSubscribers - 1)
      if (clockSubscribers === 0 && clockIntervalId !== null) {
        clearInterval(clockIntervalId)
        clockIntervalId = null
      }
    })
  }

  return time
}
