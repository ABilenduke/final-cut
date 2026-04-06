import { describe, it, expect } from 'vitest'
import { slugify } from '~/utils/slugify'

describe('slugify', () => {
  it('converts title to slug', () => {
    expect(slugify('The Dark Knight')).toBe('the-dark-knight')
  })

  it('handles special characters', () => {
    expect(slugify("Spider-Man: No Way Home")).toBe('spider-man-no-way-home')
  })

  it('trims whitespace', () => {
    expect(slugify('  hello world  ')).toBe('hello-world')
  })

  it('handles empty string', () => {
    expect(slugify('')).toBe('')
  })

  it('collapses multiple spaces', () => {
    expect(slugify('a   b   c')).toBe('a-b-c')
  })

  it('removes apostrophes', () => {
    expect(slugify("Ocean's Eleven")).toBe('oceans-eleven')
  })
})
