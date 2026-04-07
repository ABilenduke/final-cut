import { describe, it, expect } from 'vitest'
import { useToast } from '~/composables/useToast'

describe('useToast', () => {
  it('adds a toast and returns an ID', () => {
    const { show, toasts } = useToast()
    const id = show({ message: 'Hello' })
    expect(id).toBeTruthy()
    expect(toasts.value.some((t) => t.id === id)).toBe(true)
  })

  it('removes a toast by ID', () => {
    const { show, dismiss, toasts } = useToast()
    const id = show({ message: 'Remove me' })
    expect(toasts.value.some((t) => t.id === id)).toBe(true)
    dismiss(id)
    expect(toasts.value.some((t) => t.id === id)).toBe(false)
  })

  it('defaults to info type and 5000ms duration', () => {
    const { show, toasts } = useToast()
    const id = show({ message: 'Default' })
    const toast = toasts.value.find((t) => t.id === id)
    expect(toast?.type).toBe('info')
    expect(toast?.duration).toBe(5000)
  })

  it('respects custom type and duration', () => {
    const { show, toasts } = useToast()
    const id = show({ message: 'Error', type: 'error', duration: 0 })
    const toast = toasts.value.find((t) => t.id === id)
    expect(toast?.type).toBe('error')
    expect(toast?.duration).toBe(0)
  })

  it('enforces max 5 toasts', () => {
    const { show, toasts } = useToast()
    // Clear any existing toasts from other tests
    while (toasts.value.length > 0) {
      toasts.value.pop()
    }
    for (let i = 0; i < 6; i++) {
      show({ message: `Toast ${i}` })
    }
    expect(toasts.value.length).toBeLessThanOrEqual(5)
  })
})
