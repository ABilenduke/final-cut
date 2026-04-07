const FOCUSABLE_SELECTOR = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(', ')

export function useFocusTrap() {
  let container: HTMLElement | null = null
  let triggerElement: Element | null = null
  let onKeyDown: ((e: KeyboardEvent) => void) | null = null
  const inertElements: HTMLElement[] = []

  function getFocusableElements(): HTMLElement[] {
    if (!container) return []
    return Array.from(container.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR))
      .filter((el) => el.offsetParent !== null)
  }

  function activate(el: HTMLElement) {
    container = el
    triggerElement = document.activeElement

    // Set inert on all body children except the modal container's root
    for (const child of Array.from(document.body.children)) {
      if (child instanceof HTMLElement && !child.contains(container)) {
        child.setAttribute('inert', '')
        inertElements.push(child)
      }
    }

    // Focus first focusable element
    const focusable = getFocusableElements()
    const firstEl = focusable[0]
    if (firstEl) {
      firstEl.focus()
    }

    // Trap Tab / Shift+Tab
    onKeyDown = (e: KeyboardEvent) => {
      if (e.key !== 'Tab') return

      const elements = getFocusableElements()
      if (elements.length === 0) return

      const first = elements[0]!
      const last = elements[elements.length - 1]!

      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault()
          last.focus()
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault()
          first.focus()
        }
      }
    }

    document.addEventListener('keydown', onKeyDown)
  }

  function deactivate() {
    // Remove keydown listener
    if (onKeyDown) {
      document.removeEventListener('keydown', onKeyDown)
      onKeyDown = null
    }

    // Remove inert from siblings
    for (const el of inertElements) {
      el.removeAttribute('inert')
    }
    inertElements.length = 0

    // Restore focus to trigger element
    if (triggerElement && document.contains(triggerElement) && triggerElement instanceof HTMLElement) {
      triggerElement.focus()
    } else {
      // Trigger was unmounted — fall back to body with temporary tabindex
      (document.activeElement as HTMLElement)?.blur()
      const hadTabindex = document.body.hasAttribute('tabindex')
      if (!hadTabindex) {
        document.body.setAttribute('tabindex', '-1')
      }
      document.body.focus()
      if (!hadTabindex) {
        document.body.removeAttribute('tabindex')
      }
    }

    container = null
    triggerElement = null
  }

  return { activate, deactivate }
}
