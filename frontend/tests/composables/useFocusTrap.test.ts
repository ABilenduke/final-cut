import { describe, it, expect, beforeEach, afterEach } from 'vitest'
import { useFocusTrap } from '~/composables/useFocusTrap'

describe('useFocusTrap', () => {
  let container: HTMLDivElement
  let button1: HTMLButtonElement
  let button2: HTMLButtonElement
  let button3: HTMLButtonElement
  let outsideButton: HTMLButtonElement

  beforeEach(() => {
    container = document.createElement('div')
    button1 = document.createElement('button')
    button1.textContent = 'First'
    button2 = document.createElement('button')
    button2.textContent = 'Second'
    button3 = document.createElement('button')
    button3.textContent = 'Third'
    container.append(button1, button2, button3)

    outsideButton = document.createElement('button')
    outsideButton.textContent = 'Outside'

    document.body.append(outsideButton)
    document.body.append(container)
  })

  afterEach(() => {
    container.remove()
    outsideButton.remove()
  })

  it('focuses first element on activation', () => {
    const { activate, deactivate } = useFocusTrap()
    activate(container)
    expect(document.activeElement).toBe(button1)
    deactivate()
  })

  it('restores focus on deactivation', () => {
    outsideButton.focus()
    expect(document.activeElement).toBe(outsideButton)

    const { activate, deactivate } = useFocusTrap()
    activate(container)
    expect(document.activeElement).toBe(button1)

    deactivate()
    expect(document.activeElement).toBe(outsideButton)
  })

  it('sets inert on sibling elements', () => {
    const { activate, deactivate } = useFocusTrap()
    activate(container)
    expect(outsideButton.hasAttribute('inert')).toBe(true)
    deactivate()
    expect(outsideButton.hasAttribute('inert')).toBe(false)
  })
})
