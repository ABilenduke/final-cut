<script setup lang="ts">
import { fallbackSiteContacts } from '~/data/siteContacts'
import { useSiteContacts, resolveSiteContacts } from '~/composables/useSiteContent'

const { data: contactsData } = useSiteContacts()
const contacts = computed(() =>
  resolveSiteContacts(contactsData.value?.data?.contacts ?? null, fallbackSiteContacts),
)

const currentYear = new Date().getFullYear()

const navItems = [
  { label: 'Our Cinemas', href: '/locations' },
  { label: 'Contact', href: '/contact' },
  { label: 'FAQ', href: '/faq' },
  { label: 'Accessibility', href: '/accessibility' },
  { label: 'Careers', href: '/careers' },
  { label: 'Private Screenings', href: '/private-screenings' },
]

</script>

<template>
  <footer class="site-footer" role="contentinfo">
    <div class="site-footer__inner">
      <div class="site-footer__nav-section">
        <nav aria-label="Secondary">
          <ul class="site-footer__nav">
            <li v-for="item in navItems" :key="item.href">
              <NuxtLink :to="item.href" class="site-footer__nav-link">
                {{ item.label }}
              </NuxtLink>
            </li>
          </ul>
        </nav>

        <!-- Social links deferred until real URLs and platform icons are available -->
      </div>

      <div class="site-footer__info">
        <address class="site-footer__address">
          {{ contacts.footerVenueName }} &middot; {{ contacts.footerAddress }} &middot; {{ contacts.footerPhone }}
        </address>
      </div>

      <div class="site-footer__legal">
        <p>&copy; {{ currentYear }} Final Cut Theatre. All rights reserved.</p>
        <div class="site-footer__legal-links">
          <NuxtLink to="/terms" class="site-footer__legal-link">Terms</NuxtLink>
          <NuxtLink to="/privacy" class="site-footer__legal-link">Privacy</NuxtLink>
        </div>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  background-color: var(--surface-container-lowest);
  min-height: 15rem;
  padding: var(--space-3xl) 0 var(--space-xl);
}

.site-footer__inner {
  max-width: 90rem;
  margin-inline: auto;
  padding-inline: var(--space-md);
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

@media (min-width: 40rem) {
  .site-footer__inner {
    padding-inline: var(--space-xl);
  }
}

@media (min-width: 60rem) {
  .site-footer__inner {
    padding-inline: var(--space-2xl);
  }
}

/* Nav section */
.site-footer__nav-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-lg);
}

@media (min-width: 60rem) {
  .site-footer__nav-section {
    flex-direction: row;
    justify-content: space-between;
    align-items: flex-start;
  }
}

.site-footer__nav {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md) var(--space-lg);
  list-style: none;
  padding: 0;
  margin: 0;
}

.site-footer__nav-link {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  color: var(--on-surface);
  text-decoration: none;
  transition: color var(--duration-micro) var(--ease-standard);
}

.site-footer__nav-link:hover {
  color: var(--secondary);
  text-decoration: underline;
}

/* Info */
.site-footer__info {
  padding-top: var(--space-md);
}

.site-footer__address {
  font-family: var(--font-body);
  font-size: var(--type-body-sm);
  font-style: normal;
  color: var(--tertiary);
}

/* Legal */
.site-footer__legal {
  display: flex;
  flex-direction: column;
  gap: var(--space-sm);
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
}

@media (min-width: 60rem) {
  .site-footer__legal {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
  }
}

.site-footer__legal p {
  margin: 0;
}

.site-footer__legal-links {
  display: flex;
  gap: var(--space-md);
}

.site-footer__legal-link {
  font-family: var(--font-body);
  font-size: var(--type-label-md);
  color: var(--tertiary);
  text-decoration: none;
}

.site-footer__legal-link:hover {
  color: var(--on-surface);
  text-decoration: underline;
}
</style>
