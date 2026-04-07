export interface Toast {
  id: string
  message: string
  type: 'info' | 'success' | 'error'
  duration: number
}

export interface ShowToastOptions {
  message: string
  type?: Toast['type']
  duration?: number
}

const MAX_TOASTS = 5

let counter = 0
function generateId(): string {
  return `toast-${++counter}-${Date.now()}`
}

export function useToast() {
  const toasts = useState<Toast[]>('toast-queue', () => [])

  function show(options: ShowToastOptions): string {
    const id = generateId()
    const toast: Toast = {
      id,
      message: options.message,
      type: options.type ?? 'info',
      duration: options.duration ?? 5000,
    }

    // Enforce max count — dismiss oldest first
    while (toasts.value.length >= MAX_TOASTS) {
      toasts.value.shift()
    }

    toasts.value.push(toast)
    return id
  }

  function dismiss(id: string): void {
    const index = toasts.value.findIndex((t) => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  return { toasts: readonly(toasts), show, dismiss }
}
