<script setup lang="ts">
import type { FeaturedSlide } from '~/types/featured-slide'
import { hashToHue, initialsFrom } from '~/utils/posterFallback'

// ─── Props ────────────────────────────────────────────────────────────────────

const props = defineProps<{
  slides: FeaturedSlide[]
}>()

// ─── Hardcoded brand fallback (shown when API returns zero slides) ─────────────
// FTC / editorial: this is not an affiliate disclosure context — it's the
// "cinema is open" brand placeholder. Disclosure components are rendered at
// the page/layout level when needed.
const BRAND_FALLBACK: FeaturedSlide = {
  id: '__brand_fallback__',
  headline: 'The Cinematic Void',
  subHeadline: 'Two screens. One obsession. Now showing at both locations.',
  imageUrl: null,
  ctaLabel: 'See What\'s On',
  ctaHref: '/movies',
}

// ─── Resolved slide list (always at least 1 item) ─────────────────────────────

const resolvedSlides = computed<FeaturedSlide[]>(() =>
  props.slides.length > 0 ? props.slides : [BRAND_FALLBACK],
)

// Branded fallback: a seeded slide image_path may have no on-disk binary. Track
// per-slide load failures so a 404 degrades to a hashed-hue gradient + glyph
// instead of a broken-image icon. Slides with no imageUrl (e.g. the brand
// fallback) keep their existing atmospheric-overlay treatment.
const failedSlides = reactive(new Set<FeaturedSlide['id']>())

function onSlideImageError(id: FeaturedSlide['id']) {
  failedSlides.add(id)
}

function slideShowsImage(slide: FeaturedSlide): boolean {
  return Boolean(slide.imageUrl) && !failedSlides.has(slide.id)
}

// ─── CTA href safety ──────────────────────────────────────────────────────────
// cta_href is admin-supplied. Allow only http(s) absolute URLs or leading-slash
// relative paths — a `javascript:`/`data:` scheme renders no link rather than a
// navigable XSS vector. Defence-in-depth alongside the backend validator.
function safeCtaHref(href: string | null): string | null {
  if (!href) return null
  const trimmed = href.trim()
  if (trimmed.startsWith('/')) return trimmed
  try {
    const url = new URL(trimmed)
    return url.protocol === 'http:' || url.protocol === 'https:' ? trimmed : null
  }
  catch {
    return null
  }
}

const slideCount = computed(() => resolvedSlides.value.length)

// ─── Active slide index ────────────────────────────────────────────────────────

const activeIndex = ref(0)

function goTo(index: number) {
  activeIndex.value = (index + slideCount.value) % slideCount.value
}

function goNext() {
  goTo(activeIndex.value + 1)
}

function goPrev() {
  goTo(activeIndex.value - 1)
}

// ─── Reduced-motion detection ─────────────────────────────────────────────────
// Read once on client mount. We do NOT reactively watch the media query because
// users rarely change this setting mid-session, and a ref is simpler than a
// MutationObserver for this use case.

const prefersReducedMotion = ref(false)

onMounted(() => {
  prefersReducedMotion.value =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
})

// ─── Auto-advance (7s interval) ───────────────────────────────────────────────
// Pauses when:
//   • pointer is over the carousel (pointerenter)
//   • keyboard focus is inside the carousel (focusin)
//   • the browser tab is hidden (visibilitychange)
// Resumes on the inverse events.

const isPaused = ref(false)
let advanceTimer: ReturnType<typeof setInterval> | null = null

function startAutoAdvance() {
  if (prefersReducedMotion.value) return
  if (slideCount.value <= 1) return
  stopAutoAdvance()
  advanceTimer = setInterval(goNext, 7000)
}

function stopAutoAdvance() {
  if (advanceTimer !== null) {
    clearInterval(advanceTimer)
    advanceTimer = null
  }
}

function onPointerEnter() {
  isPaused.value = true
  stopAutoAdvance()
}

function onPointerLeave() {
  isPaused.value = false
  startAutoAdvance()
}

function onFocusIn() {
  isPaused.value = true
  stopAutoAdvance()
}

function onFocusOut(e: FocusEvent) {
  // Only resume if focus truly left the carousel
  const carousel = e.currentTarget as HTMLElement
  if (!carousel.contains(e.relatedTarget as Node | null)) {
    isPaused.value = false
    startAutoAdvance()
  }
}

function onVisibilityChange() {
  if (document.hidden) {
    isPaused.value = true
    stopAutoAdvance()
  } else {
    isPaused.value = false
    startAutoAdvance()
  }
}

onMounted(() => {
  startAutoAdvance()
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(() => {
  stopAutoAdvance()
  document.removeEventListener('visibilitychange', onVisibilityChange)
})

// ─── ARIA live region ─────────────────────────────────────────────────────────
// "polite" while paused so screen readers announce the current slide.
// "off" while auto-advancing to prevent constant interruptions.

const ariaLive = computed<'polite' | 'off'>(() =>
  isPaused.value ? 'polite' : 'off',
)
</script>

<template>
  <section
    class="carousel"
    role="region"
    aria-roledescription="carousel"
    aria-label="Featured"
    @pointerenter="onPointerEnter"
    @pointerleave="onPointerLeave"
    @focusin="onFocusIn"
    @focusout="onFocusOut"
  >
    <!-- Slide container — scroll-snap handles touch swipe -->
    <div
      class="carousel__track"
      :aria-live="ariaLive"
    >
      <div
        v-for="(slide, index) in resolvedSlides"
        :key="slide.id"
        role="group"
        aria-roledescription="slide"
        :aria-label="`Slide ${index + 1} of ${slideCount}: ${slide.headline}`"
        class="carousel__slide"
        :class="{
          'carousel__slide--active': index === activeIndex,
          'carousel__slide--reduced-motion': prefersReducedMotion,
        }"
      >
        <!-- Background image (with branded gradient fallback on load failure) -->
        <img
          v-if="slideShowsImage(slide)"
          :src="slide.imageUrl"
          alt=""
          aria-hidden="true"
          class="carousel__slide-bg"
          :loading="index === 0 ? 'eager' : 'lazy'"
          :fetchpriority="index === 0 ? 'high' : 'auto'"
          @error="onSlideImageError(slide.id)"
        />
        <div
          v-else-if="slide.imageUrl"
          class="carousel__slide-fallback"
          :style="{ '--fallback-hue': hashToHue(slide.headline) }"
          aria-hidden="true"
        >
          <span class="carousel__slide-glyph">{{ initialsFrom(slide.headline) }}</span>
        </div>

        <!-- Atmospheric overlay — vignette bloom from primary-container -->
        <div class="carousel__slide-overlay" aria-hidden="true" />

        <!-- Content -->
        <div class="carousel__slide-inner">
          <h2 class="carousel__headline">{{ slide.headline }}</h2>
          <p v-if="slide.subHeadline" class="carousel__sub">
            {{ slide.subHeadline }}
          </p>
          <NuxtLink
            v-if="slide.ctaLabel && safeCtaHref(slide.ctaHref)"
            :to="safeCtaHref(slide.ctaHref)"
            class="carousel__cta"
          >
            {{ slide.ctaLabel }}
            <span aria-hidden="true">&nbsp;&rarr;</span>
          </NuxtLink>
        </div>
      </div>
    </div>

    <!-- Prev / Next navigation — always visible on desktop, hidden on mobile -->
    <div class="carousel__nav" aria-hidden="false">
      <button
        type="button"
        class="carousel__nav-btn carousel__nav-btn--prev"
        aria-label="Previous slide"
        @click="goPrev"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
      <button
        type="button"
        class="carousel__nav-btn carousel__nav-btn--next"
        aria-label="Next slide"
        @click="goNext"
      >
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <!-- Dot indicators -->
    <div
      v-if="slideCount > 1"
      class="carousel__dots"
      role="group"
      aria-label="Slide navigation"
    >
      <button
        v-for="(slide, index) in resolvedSlides"
        :key="slide.id"
        type="button"
        class="carousel__dot"
        :class="{ 'carousel__dot--active': index === activeIndex }"
        :aria-label="`Go to slide ${index + 1}`"
        :aria-current="index === activeIndex ? 'true' : 'false'"
        @click="goTo(index)"
      />
    </div>
  </section>
</template>

<style scoped>
/* ─── Container ────────────────────────────────────────────────────────────── */

.carousel {
  position: relative;
  width: 100%;
  overflow: hidden;
  background-color: var(--surface-container-lowest);
  isolation: isolate;
}

/* ─── Track ─────────────────────────────────────────────────────────────────── */

.carousel__track {
  position: relative;
  min-height: 90vh;
  min-height: clamp(28rem, 90vh, 66rem);
}

/* ─── Slide ─────────────────────────────────────────────────────────────────── */

.carousel__slide {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;

  /* Hidden by default — overridden for the active slide */
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--duration-emphasis) var(--ease-standard);
}

.carousel__slide--active {
  opacity: 1;
  pointer-events: auto;
}

/* Reduced motion: no crossfade; all slides are instant-cut via opacity 0/1.
   The active slide is still visible but there is no interpolated transition. */
@media (prefers-reduced-motion: reduce) {
  .carousel__slide {
    transition: none;
  }
}

/* Background image */
.carousel__slide-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center 60%;
  z-index: var(--z-recessed);
}

/* Branded gradient fallback when a slide image fails to load */
.carousel__slide-fallback {
  position: absolute;
  inset: 0;
  z-index: var(--z-recessed);
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(
    135deg,
    hsl(var(--fallback-hue, 0) 38% 20%),
    hsl(var(--fallback-hue, 0) 30% 6%)
  );
}

.carousel__slide-glyph {
  font-family: var(--font-display);
  font-size: clamp(6rem, 16vw, 12rem);
  font-style: italic;
  color: var(--primary-container);
  opacity: 0.5;
  letter-spacing: -0.04em;
  line-height: 1;
}

/* Vignette bloom overlay: primary-container → transparent → surface */
.carousel__slide-overlay {
  position: absolute;
  inset: 0;
  z-index: 1;
  background:
    radial-gradient(ellipse 80% 60% at 50% 0%, rgb(var(--primary-container-rgb) / 0.35), transparent 60%),
    linear-gradient(180deg, transparent 30%, var(--surface-container-lowest) 100%);
}

/* Content */
.carousel__slide-inner {
  position: relative;
  z-index: 2;
  max-width: 90rem;
  margin: 0 auto;
  width: 100%;
  padding: var(--space-2xl) var(--space-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
  align-items: flex-start;
}

@media (min-width: 40rem) {
  .carousel__slide-inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .carousel__slide-inner {
    padding-inline: var(--space-2xl);
    padding-bottom: var(--space-3xl);
  }
}

/* ─── Typography ─────────────────────────────────────────────────────────────── */

.carousel__headline {
  font-family: var(--font-display);
  font-weight: 500;
  font-size: clamp(2.25rem, 1.25rem + 2.5vw, 3.5rem);
  line-height: 0.95;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  text-wrap: balance;
  max-width: 20ch;
  margin: 0;
}

.carousel__sub {
  font-family: var(--font-body);
  font-size: 1.125rem;
  line-height: 1.5;
  color: var(--tertiary);
  max-width: 48ch;
  text-wrap: pretty;
  margin: 0;
}

/* ─── CTA ────────────────────────────────────────────────────────────────────── */

.carousel__cta {
  display: inline-flex;
  align-items: center;
  height: var(--control-height);
  padding-inline: var(--space-lg);
  background-color: var(--primary-container);
  color: var(--secondary);
  font-family: var(--font-body);
  font-size: 1rem;
  font-weight: 500;
  text-decoration: none;
  border-radius: var(--radius-sm);
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

.carousel__cta:hover {
  background-color: var(--primary-container-hover);
}

.carousel__cta:active {
  background-color: var(--primary-container-active);
}

.carousel__cta:focus-visible {
  /* Double-ring gold focus indicator per design system */
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}

/* ─── Prev / Next buttons ────────────────────────────────────────────────────── */

.carousel__nav {
  position: absolute;
  inset: 0;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-inline: var(--space-sm);
  z-index: 3;
}

@media (min-width: 40rem) {
  .carousel__nav {
    padding-inline: var(--space-md);
  }
}

.carousel__nav-btn {
  pointer-events: auto;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 3rem;
  height: 3rem;
  background-color: rgb(var(--surface-variant-rgb) / var(--opacity-glass));
  color: var(--on-surface);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition:
    background-color var(--duration-micro) var(--ease-standard),
    color var(--duration-micro) var(--ease-standard);
}

/* On mobile, hide the prev/next buttons — swipe is the affordance */
@media (max-width: 39.9375rem) {
  .carousel__nav-btn {
    display: none;
  }
}

.carousel__nav-btn:hover {
  background-color: rgb(var(--surface-variant-rgb) / 0.9);
  color: var(--secondary);
}

.carousel__nav-btn:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}

@supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
  .carousel__nav-btn {
    background-color: rgb(var(--surface-variant-rgb) / var(--opacity-glass));
    -webkit-backdrop-filter: blur(0.75rem);
    backdrop-filter: blur(0.75rem);
  }
}

/* ─── Dot indicators ─────────────────────────────────────────────────────────── */

.carousel__dots {
  position: absolute;
  bottom: var(--space-lg);
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: var(--space-sm);
  z-index: 3;
}

.carousel__dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-sm);
  background-color: rgb(var(--on-surface-rgb, 229 226 225) / 0.4);
  border: none;
  cursor: pointer;
  transition:
    background-color var(--duration-standard) var(--ease-standard),
    width var(--duration-standard) var(--ease-standard);
  padding: 0;
}

.carousel__dot--active {
  background-color: var(--secondary);
  width: 1.5rem;
}

.carousel__dot:focus-visible {
  outline: none;
  box-shadow:
    0 0 0 0.125rem var(--surface),
    0 0 0 0.25rem var(--secondary);
}

/* ─── Reduced-motion static stack ──────────────────────────────────────────────
   When user prefers reduced motion, the auto-advance is disabled (JS checks
   matchMedia before setting interval). All slides stack; only the active
   one is visible at opacity 1. Dot buttons serve as manual navigation. */

@media (prefers-reduced-motion: reduce) {
  .carousel__slide {
    transition: none;
  }
}
</style>
