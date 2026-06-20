<script setup lang="ts">
const props = withDefaults(defineProps<{
  variant?: 'primary' | 'secondary' | 'tertiary' | 'gold'
  size?: 'sm' | 'default' | 'lg'
  disabled?: boolean
  loading?: boolean
  block?: boolean
  type?: 'button' | 'submit' | 'reset'
  href?: string
}>(), {
  variant: 'primary',
  size: 'default',
  disabled: false,
  loading: false,
  block: false,
  type: 'button',
})

const emit = defineEmits<{
  click: [event: MouseEvent]
}>()

defineOptions({ inheritAttrs: false })

const isInteractive = computed(() => !props.disabled && !props.loading)

// primary / gold / secondary are depressible "push" variants — a 3-layer
// key (shadow / edge / face). Only tertiary (ghost text) stays flat.
const isPushable = computed(() => props.variant !== 'tertiary')

const tag = computed(() => {
  if (props.href && isInteractive.value) return resolveComponent('NuxtLink')
  return 'button'
})

function handleClick(event: MouseEvent) {
  if (!isInteractive.value) {
    event.preventDefault()
    return
  }
  emit('click', event)
}
</script>

<template>
  <component
    :is="tag"
    v-bind="$attrs"
    :to="href && isInteractive ? href : undefined"
    :type="tag === 'button' ? type : undefined"
    :disabled="tag === 'button' ? (disabled || loading || undefined) : undefined"
    :aria-disabled="disabled || loading || undefined"
    :aria-busy="loading || undefined"
    :tabindex="disabled ? -1 : undefined"
    class="cv-button"
    :class="[
      `cv-button--${variant}`,
      `cv-button--${size}`,
      {
        'cv-button--pushable': isPushable,
        'cv-button--block': block,
        'cv-button--disabled': disabled,
        'cv-button--loading': loading,
      },
    ]"
    @click="handleClick"
  >
    <!-- Pushable variants (primary / gold): shadow + edge + pressable face -->
    <template v-if="isPushable">
      <span class="cv-button__shadow" aria-hidden="true" />
      <span class="cv-button__edge" aria-hidden="true" />
      <span class="cv-button__face">
        <span v-if="$slots['icon-left']" class="cv-button__icon cv-button__icon--left" aria-hidden="true">
          <slot name="icon-left" />
        </span>
        <span class="cv-button__label">
          <slot />
        </span>
        <span v-if="$slots['icon-right']" class="cv-button__icon cv-button__icon--right" aria-hidden="true">
          <slot name="icon-right" />
        </span>
        <span v-if="loading" class="cv-button__spinner" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z" />
          </svg>
        </span>
      </span>
    </template>

    <!-- Flat variants (secondary / tertiary ghost) -->
    <template v-else>
      <span v-if="$slots['icon-left']" class="cv-button__icon cv-button__icon--left" aria-hidden="true">
        <slot name="icon-left" />
      </span>
      <span class="cv-button__label">
        <slot />
      </span>
      <span v-if="$slots['icon-right']" class="cv-button__icon cv-button__icon--right" aria-hidden="true">
        <slot name="icon-right" />
      </span>
      <span v-if="loading" class="cv-button__spinner" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z" />
        </svg>
      </span>
    </template>
  </component>
</template>

<style scoped>
.cv-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-sm);
  border: none;
  font-family: var(--font-body);
  line-height: 1;
  cursor: pointer;
  text-decoration: none;
  position: relative;
  -webkit-tap-highlight-color: transparent;
}

.cv-button--block {
  width: 100%;
}

/* A pushable button's socket (edge + shadow) is absolutely positioned to the
   root, so a flex/grid parent that stretches the root wider than the face
   would expose the side-wall as a full-width band. Shrink-wrap to content
   unless the button is explicitly `block` (intentional full-width key). */
.cv-button--pushable:not(.cv-button--block) {
  width: fit-content;
  max-width: 100%;
}

/* ————————————————————————————————————————————————
   Flat variant — tertiary (ghost)
   ———————————————————————————————————————————————— */
/* Tertiary = ghost: a serif text action with an underline that draws in
   from the left and goes gold on hover (per the Push Button design). */
.cv-button--tertiary {
  height: var(--control-height);
  padding-inline: var(--space-xs);
  background: transparent;
  color: var(--on-surface);
  font-family: var(--font-display);
  font-size: var(--type-body-md);
}

.cv-button--tertiary::after {
  content: '';
  position: absolute;
  left: var(--space-xs);
  right: var(--space-xs);
  bottom: 0.85rem;
  height: var(--border-hairline);
  background: rgba(229, 226, 225, 0.55); /* on-surface @ 55% — invisible until hover */
  transform: scaleX(0);
  transform-origin: left;
  transition:
    transform var(--duration-standard) var(--ease-standard),
    background var(--duration-standard) var(--ease-standard);
}

.cv-button--tertiary:hover:not(.cv-button--disabled):not(.cv-button--loading) {
  color: var(--secondary);
}

.cv-button--tertiary:hover:not(.cv-button--disabled):not(.cv-button--loading)::after {
  background: var(--secondary);
  transform: scaleX(1);
}

/* Flat-variant (tertiary) sizes */
.cv-button--tertiary.cv-button--lg {
  height: var(--control-height-lg);
  font-size: var(--type-body-lg);
}

@media (pointer: fine) {
  .cv-button--tertiary.cv-button--sm {
    height: var(--control-height-sm);
    font-size: var(--type-body-sm);
  }
}

/* ————————————————————————————————————————————————
   Pushable variants — the depressible key
     .cv-button__shadow → ground shadow cast on the floor
     .cv-button__edge   → colored side-wall (the button's thickness)
     .cv-button__face   → the pressable top surface
   On :active the face travels down its wall into the socket and the
   ground shadow tightens, so it reads as a real key bottoming out.
   The gradient stops below are local to this depressible metaphor and
   intentionally live here rather than in the global palette.
   ———————————————————————————————————————————————— */
.cv-button--pushable {
  --push-radius: 0.4375rem;       /* 7px — physical key corner */
  --push-lift: 0.4375rem;         /* 7px resting lift over the wall */
  --push-lift-hover: 0.5625rem;   /* 9px */
  --push-lift-press: 0.125rem;    /* 2px — pressed into the socket */
  --push-height: 3.4rem;

  /* maroon (primary) defaults */
  --push-edge: linear-gradient(180deg, #2c0000 0%, #1a0000 100%);
  --push-face: linear-gradient(180deg, #74100f 0%, #5a0808 52%, #4c0606 100%);
  --push-face-hover: linear-gradient(180deg, #851513 0%, #680b0b 52%, #560808 100%);
  --push-label: var(--secondary);
  --push-face-inset:
    inset 0 0.0625rem 0 rgba(255, 196, 150, 0.22), /* top sheen */
    inset 0 -0.0625rem 0 rgba(0, 0, 0, 0.35);      /* bottom catch */
  --push-text-shadow: 0 0.0625rem 0 rgba(0, 0, 0, 0.45);

  background: transparent;
  padding: 0;
  border-radius: var(--push-radius);
  outline-offset: 0.25rem; /* clears the lifted face */
}

/* gold variant — same mechanism, gold face + dark label */
.cv-button--gold {
  --push-edge: linear-gradient(180deg, #5a4f12 0%, #3a330b 100%);
  --push-face: linear-gradient(180deg, #e6d27a 0%, #cdb74f 52%, #b9a23e 100%);
  --push-face-hover: linear-gradient(180deg, #f0dd86 0%, #d8c259 52%, #c4ad45 100%);
  --push-label: #2a2410;
  --push-face-inset:
    inset 0 0.0625rem 0 rgba(255, 255, 255, 0.45),
    inset 0 -0.0625rem 0 rgba(0, 0, 0, 0.25);
  --push-text-shadow: 0 0.0625rem 0 rgba(255, 255, 255, 0.25);
}

/* secondary variant — low-emphasis neutral graphite key (same mechanism,
   no colour: a calm tactile button for Cancel / Call / RSVP / utilities) */
.cv-button--secondary {
  --push-edge: linear-gradient(180deg, #161616 0%, #0d0d0d 100%);
  --push-face: linear-gradient(180deg, #333333 0%, #2a2a2a 55%, #232323 100%);
  --push-face-hover: linear-gradient(180deg, #3b3b3b 0%, #303030 55%, #292929 100%);
  --push-label: var(--on-surface);
  --push-face-inset:
    inset 0 0.0625rem 0 rgba(255, 255, 255, 0.06),
    inset 0 -0.0625rem 0 rgba(0, 0, 0, 0.4);
  --push-text-shadow: 0 0.0625rem 0 rgba(0, 0, 0, 0.5);
}

.cv-button--pushable.cv-button--lg {
  --push-height: 3.8rem;
}

@media (pointer: fine) {
  .cv-button--pushable.cv-button--sm {
    --push-height: 2.8rem;
  }
}

.cv-button__shadow {
  position: absolute;
  inset: 0;
  border-radius: var(--push-radius);
  background: rgba(0, 0, 0, 0.55);
  filter: blur(1px);
  transform: translateY(0.5rem); /* 8px */
  transition:
    transform 200ms var(--ease-standard),
    filter 200ms var(--ease-standard),
    opacity 200ms var(--ease-standard);
  will-change: transform;
}

.cv-button__edge {
  position: absolute;
  inset: 0;
  border-radius: var(--push-radius);
  background: var(--push-edge);
}

.cv-button__face {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  padding: 0 var(--space-xl);
  height: var(--push-height);
  border-radius: var(--push-radius);
  background: var(--push-face);
  color: var(--push-label);
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 500;
  letter-spacing: 0.015em;
  text-shadow: var(--push-text-shadow);
  box-shadow: var(--push-face-inset);
  transform: translateY(calc(-1 * var(--push-lift)));
  transition:
    transform 120ms var(--ease-standard),
    background 200ms var(--ease-standard),
    filter 200ms var(--ease-standard);
  will-change: transform;
}

.cv-button--block.cv-button--pushable .cv-button__face {
  width: 100%;
}

.cv-button__face .cv-button__icon {
  transition: transform 200ms var(--ease-standard);
}

/* hover — face warms + lifts a hair, gold catches light, icon nudges out */
.cv-button--pushable:hover:not(.cv-button--disabled):not(.cv-button--loading) .cv-button__face {
  background: var(--push-face-hover);
  filter: brightness(1.04);
  transform: translateY(calc(-1 * var(--push-lift-hover)));
}

.cv-button--pushable:hover:not(.cv-button--disabled):not(.cv-button--loading) .cv-button__shadow {
  transform: translateY(0.625rem); /* 10px */
  filter: blur(2px);
}

.cv-button--pushable:hover:not(.cv-button--disabled):not(.cv-button--loading) .cv-button__icon {
  transform: translateX(0.25rem);
}

/* press — face sinks into the socket, wall almost vanishes, shadow tightens */
.cv-button--pushable:active:not(.cv-button--disabled):not(.cv-button--loading) .cv-button__face {
  transform: translateY(calc(-1 * var(--push-lift-press)));
  transition-duration: 34ms;
  filter: brightness(0.96);
}

.cv-button--pushable:active:not(.cv-button--disabled):not(.cv-button--loading) .cv-button__shadow {
  transform: translateY(0.1875rem); /* 3px */
  filter: blur(0.5px);
  opacity: 0.7;
  transition-duration: 34ms;
}

/* ————————————————————————————————————————————————
   Shared states + sub-elements
   ———————————————————————————————————————————————— */
.cv-button--disabled {
  cursor: not-allowed;
  opacity: var(--opacity-disabled);
}

.cv-button--loading {
  cursor: wait;
}

.cv-button--loading .cv-button__label,
.cv-button--loading .cv-button__icon {
  visibility: hidden;
}

.cv-button__icon {
  display: flex;
  align-items: center;
}

.cv-button__spinner {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.cv-button__spinner svg {
  width: var(--icon-md);
  height: var(--icon-md);
  animation: cv-button-spin 750ms linear infinite;
}

@keyframes cv-button-spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
