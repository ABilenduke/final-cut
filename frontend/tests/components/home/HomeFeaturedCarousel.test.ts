import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import HomeFeaturedCarousel from '~/components/home/HomeFeaturedCarousel.vue'
import type { FeaturedSlide } from '~/types/featured-slide'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeSlide(overrides: Partial<FeaturedSlide> = {}): FeaturedSlide {
  return {
    id: overrides.id ?? 1,
    headline: overrides.headline ?? 'Festival Week',
    sub_headline: overrides.sub_headline ?? 'Seven films. Seven nights.',
    image_url: overrides.image_url ?? 'https://example.com/slide.jpg',
    cta_label: overrides.cta_label ?? 'Get Tickets',
    cta_href: overrides.cta_href ?? '/movies',
    ...overrides,
  }
}

// ─── matchMedia mock ─────────────────────────────────────────────────────────
// jsdom doesn't implement window.matchMedia; provide a minimal stub.

function mockMatchMedia(prefersReducedMotion: boolean) {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    configurable: true,
    value: vi.fn((query: string) => ({
      matches: query === '(prefers-reduced-motion: reduce)' ? prefersReducedMotion : false,
      media: query,
      onchange: null,
      addListener: vi.fn(),
      removeListener: vi.fn(),
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
      dispatchEvent: vi.fn(),
    })),
  })
}

// ─── Tests ────────────────────────────────────────────────────────────────────

describe('HomeFeaturedCarousel', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    mockMatchMedia(false) // Default: no reduced motion preference
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.restoreAllMocks()
  })

  // ── (a) Slide rendering ────────────────────────────────────────────────────

  it('(a1) renders all slides passed via props', async () => {
    const slides = [makeSlide({ id: 1, headline: 'Slide One' }), makeSlide({ id: 2, headline: 'Slide Two' })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    // Use the slide-specific selector to avoid matching the dot indicators group
    const renderedSlides = wrapper.findAll('[role="group"][aria-roledescription="slide"]')
    expect(renderedSlides).toHaveLength(2)
    expect(wrapper.html()).toContain('Slide One')
    expect(wrapper.html()).toContain('Slide Two')
  })

  it('(a2) only the first slide is active on initial render', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const slideEls = wrapper.findAll('.carousel__slide')
    expect(slideEls[0].classes()).toContain('carousel__slide--active')
    expect(slideEls[1].classes()).not.toContain('carousel__slide--active')
  })

  // ── (b) Empty state — brand fallback ──────────────────────────────────────

  it('(b) empty slides renders the hardcoded brand fallback slide', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides: [] } })

    const renderedSlides = wrapper.findAll('[role="group"][aria-roledescription="slide"]')
    expect(renderedSlides).toHaveLength(1)
    expect(wrapper.html()).toContain('The Cinematic Void')
    // CTA links to /movies
    const cta = wrapper.find('.carousel__cta')
    expect(cta.exists()).toBe(true)
    expect(cta.attributes('href')).toBe('/movies')
  })

  // ── (c) ARIA attributes ────────────────────────────────────────────────────

  it('(c1) root element has role=region, aria-roledescription=carousel, aria-label=Featured', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide()] },
    })
    const root = wrapper.find('[role="region"]')
    expect(root.exists()).toBe(true)
    expect(root.attributes('aria-roledescription')).toBe('carousel')
    expect(root.attributes('aria-label')).toBe('Featured')
  })

  it('(c2) each slide panel has role=group, aria-roledescription=slide', async () => {
    const slides = [makeSlide({ id: 1, headline: 'Alpha' }), makeSlide({ id: 2, headline: 'Beta' })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    // Scope to slide panels only (the dot nav group also uses role="group")
    const panels = wrapper.findAll('[role="group"][aria-roledescription="slide"]')
    expect(panels).toHaveLength(2)
    for (const panel of panels) {
      expect(panel.attributes('aria-roledescription')).toBe('slide')
    }
  })

  it('(c3) each slide aria-label includes position and headline', async () => {
    const slides = [
      makeSlide({ id: 1, headline: 'Festival Week' }),
      makeSlide({ id: 2, headline: 'Premier Members Night' }),
    ]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const panels = wrapper.findAll('[role="group"][aria-roledescription="slide"]')
    expect(panels[0].attributes('aria-label')).toBe('Slide 1 of 2: Festival Week')
    expect(panels[1].attributes('aria-label')).toBe('Slide 2 of 2: Premier Members Night')
  })

  it('(c4) aria-live on track is off while auto-advancing (not paused)', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide(), makeSlide({ id: 2 })] },
    })
    const track = wrapper.find('.carousel__track')
    // Default: not paused → auto-advancing → "off"
    expect(track.attributes('aria-live')).toBe('off')
  })

  it('(c5) aria-live becomes polite when carousel is paused via pointerenter', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide(), makeSlide({ id: 2 })] },
    })
    const root = wrapper.find('[role="region"]')
    await root.trigger('pointerenter')

    const track = wrapper.find('.carousel__track')
    expect(track.attributes('aria-live')).toBe('polite')
  })

  // ── (d) Dot navigation ────────────────────────────────────────────────────

  it('(d1) renders one dot per slide when slide count > 1', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 }), makeSlide({ id: 3 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const dots = wrapper.findAll('.carousel__dot')
    expect(dots).toHaveLength(3)
  })

  it('(d2) does not render dots when there is only one slide', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide()] },
    })
    expect(wrapper.find('.carousel__dots').exists()).toBe(false)
  })

  it('(d3) first dot has aria-current=true, others false', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const dots = wrapper.findAll('.carousel__dot')
    expect(dots[0].attributes('aria-current')).toBe('true')
    expect(dots[1].attributes('aria-current')).toBe('false')
  })

  it('(d4) clicking a dot advances to that slide and updates aria-current', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 }), makeSlide({ id: 3 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const dots = wrapper.findAll('.carousel__dot')
    await dots[2].trigger('click')

    const updatedDots = wrapper.findAll('.carousel__dot')
    expect(updatedDots[2].attributes('aria-current')).toBe('true')
    expect(updatedDots[0].attributes('aria-current')).toBe('false')

    const slideEls = wrapper.findAll('.carousel__slide')
    expect(slideEls[2].classes()).toContain('carousel__slide--active')
    expect(slideEls[0].classes()).not.toContain('carousel__slide--active')
  })

  it('(d5) each dot has aria-label="Go to slide N"', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    const dots = wrapper.findAll('.carousel__dot')
    expect(dots[0].attributes('aria-label')).toBe('Go to slide 1')
    expect(dots[1].attributes('aria-label')).toBe('Go to slide 2')
  })

  // ── (e) Next / Prev buttons ───────────────────────────────────────────────

  it('(e1) next button has aria-label="Next slide"', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide(), makeSlide({ id: 2 })] },
    })
    const nextBtn = wrapper.find('.carousel__nav-btn--next')
    expect(nextBtn.attributes('aria-label')).toBe('Next slide')
  })

  it('(e2) prev button has aria-label="Previous slide"', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide(), makeSlide({ id: 2 })] },
    })
    const prevBtn = wrapper.find('.carousel__nav-btn--prev')
    expect(prevBtn.attributes('aria-label')).toBe('Previous slide')
  })

  it('(e3) clicking next advances to the next slide', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    await wrapper.find('.carousel__nav-btn--next').trigger('click')

    const slideEls = wrapper.findAll('.carousel__slide')
    expect(slideEls[1].classes()).toContain('carousel__slide--active')
    expect(slideEls[0].classes()).not.toContain('carousel__slide--active')
  })

  it('(e4) clicking prev wraps to the last slide from the first', async () => {
    const slides = [makeSlide({ id: 1 }), makeSlide({ id: 2 }), makeSlide({ id: 3 })]
    const wrapper = await mountSuspended(HomeFeaturedCarousel, { props: { slides } })

    await wrapper.find('.carousel__nav-btn--prev').trigger('click')

    const slideEls = wrapper.findAll('.carousel__slide')
    expect(slideEls[2].classes()).toContain('carousel__slide--active')
  })

  // ── (f) Reduced motion — no auto-advance ─────────────────────────────────

  it('(f) reduced-motion: setInterval is NOT called when matchMedia returns reduce', async () => {
    mockMatchMedia(true) // Signal: prefers-reduced-motion: reduce
    const setIntervalSpy = vi.spyOn(globalThis, 'setInterval')

    await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide({ id: 1 }), makeSlide({ id: 2 })] },
    })

    // The carousel's startAutoAdvance bails out early when reduced motion is detected
    expect(setIntervalSpy).not.toHaveBeenCalled()
  })

  // ── (g) CTA rendering ─────────────────────────────────────────────────────

  it('(g1) renders a CTA link when cta_label and cta_href are present', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide({ cta_label: 'Book Now', cta_href: '/purchase' })] },
    })
    const cta = wrapper.find('.carousel__cta')
    expect(cta.exists()).toBe(true)
    expect(cta.text()).toContain('Book Now')
  })

  it('(g2) omits CTA when cta_label or cta_href is null', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide({ cta_label: null, cta_href: null })] },
    })
    expect(wrapper.find('.carousel__cta').exists()).toBe(false)
  })

  // ── (h) Sub-headline ──────────────────────────────────────────────────────

  it('(h1) renders sub_headline when present', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide({ sub_headline: 'Seven films, seven nights.' })] },
    })
    expect(wrapper.find('.carousel__sub').exists()).toBe(true)
    expect(wrapper.find('.carousel__sub').text()).toBe('Seven films, seven nights.')
  })

  it('(h2) omits sub-headline element when sub_headline is null', async () => {
    const wrapper = await mountSuspended(HomeFeaturedCarousel, {
      props: { slides: [makeSlide({ sub_headline: null })] },
    })
    expect(wrapper.find('.carousel__sub').exists()).toBe(false)
  })
})
