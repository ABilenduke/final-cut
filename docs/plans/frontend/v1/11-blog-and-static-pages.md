# Plan 11: Blog & Static Content Pages

> **Priority:** Could Have
> **Complexity:** S
> **Depends On:** Plan 03 (UI primitives), Plan 04 (layouts)
> **Unlocks:** None (leaf node)

## Overview

Add blog functionality via `@nuxt/content` and static informational pages (Careers, Accessibility). These are the lowest-priority pages — nice to have for SEO and community engagement but not required for the core movie/ticket experience.

## Reference Documents

- `docs/COMPONENT_INVENTORY.md` — BlogPostCard
- `docs/PAGE_SPECS.md` — Blog, Blog Post, Careers, Accessibility

---

## Tasks

### Task 1: @nuxt/content Setup

- **MoSCoW:** Could Have
- **Complexity:** S
- **Files:**
  - `frontend/nuxt.config.ts` — Add @nuxt/content module
  - `frontend/content/blog/` — Directory for markdown blog posts
  - Sample blog posts (2-3 markdown files with frontmatter)
- **Details:**
  Configure @nuxt/content for blog markdown. Frontmatter schema: `title`, `slug`, `excerpt`, `date`, `author`, `imageUrl`, `tags`. Content directory: `content/blog/`.

- **Acceptance Criteria:**
  - [ ] @nuxt/content module configured
  - [ ] Sample blog posts render correctly
  - [ ] `queryContent` API works for listing and fetching

---

### Task 2: BlogPostCard

- **MoSCoW:** Could Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/components/content/BlogPostCard.vue`
- **Details:**
  CvCard with featured image (16:9), title, excerpt, date, author. Links to `/blog/:slug`.

  **Props:** `post: { title, slug, excerpt, date, author, imageUrl }`

- **Acceptance Criteria:**
  - [ ] Image renders at 16:9 aspect ratio
  - [ ] Title, excerpt, date, and author display
  - [ ] Card links to blog post page

---

### Task 3: Blog Pages

- **MoSCoW:** Could Have
- **Complexity:** S
- **Files:**
  - `frontend/app/pages/blog/index.vue` — Blog listing (Ensemble grid)
  - `frontend/app/pages/blog/[slug].vue` — Blog post (Close-Up)
- **Details:**
  **Listing:** Ensemble grid of BlogPostCards. Data via `queryContent('blog')`. SEO: `Blog` structured data.

  **Post:** Close-Up composition. Title, author, date, featured image, rendered markdown body, related posts. SEO: `Article` structured data.

- **Acceptance Criteria:**
  - [ ] Blog listing renders grid of posts
  - [ ] Blog post renders markdown content
  - [ ] Related posts section (by tags or recent)
  - [ ] Structured data (Blog, Article)

---

### Task 4: Static Pages (Careers, Accessibility)

- **MoSCoW:** Could Have
- **Complexity:** XS
- **Files:**
  - `frontend/app/pages/careers.vue`
  - `frontend/app/pages/accessibility.vue`
- **Details:**
  **Careers:** Close-Up, prerendered. Intro text, current openings, benefits, application instructions. SEO: `JobPosting` structured data.

  **Accessibility:** Close-Up, prerendered. Commitment statement, assisted listening, wheelchair seating, open captions (link to `/whats-on?accessibility=open_caption`), audio description (link to `/whats-on?accessibility=audio_described`), sensory-friendly (link to `/whats-on?accessibility=sensory_friendly`), service animal policy, contact for accommodations.

- **Acceptance Criteria:**
  - [ ] Both pages render with correct content
  - [ ] Accessibility page links to pre-filtered calendar views
  - [ ] Prerendered (no server-side data fetching needed)
  - [ ] Careers has JobPosting structured data

---

## Testing Requirements

- **E2E:** Blog listing → post navigation. Verify structured data.
- **Links:** Accessibility page links to correct filtered calendar URLs.

## Risks & Open Questions

1. **@nuxt/content compatibility** — Verify works with Nuxt 4. If issues, blog content can be static TypeScript data instead.
