/**
 * Live wall-clock time as a reactive HH:MM:SS string.
 * SSR renders a static placeholder so hydration does not mismatch; the client
 * starts ticking in onMounted and cleans up in onUnmounted.
 */
export function useClock() {
  const time = ref('--:--:--')

  if (import.meta.client) {
    const pad = (n: number) => String(n).padStart(2, '0')
    const tick = () => {
      const d = new Date()
      time.value = `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    }

    let intervalId: ReturnType<typeof setInterval> | null = null

    onMounted(() => {
      tick()
      intervalId = setInterval(tick, 1000)
    })

    onUnmounted(() => {
      if (intervalId !== null) {
        clearInterval(intervalId)
        intervalId = null
      }
    })
  }

  return time
}
