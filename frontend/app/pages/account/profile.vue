<script setup lang="ts">
definePageMeta({ layout: 'account', middleware: 'auth' })
useHead({
  title: 'Edit Profile — Final Cut',
  meta: [{ name: 'robots', content: 'noindex' }],
})

const { profile, updateProfile } = useAccount()
const { show } = useToast()

const { data: profileData, refresh } = await profile()
const saving = ref(false)

async function handleSave(data: Record<string, unknown>) {
  saving.value = true
  try {
    await updateProfile(data)
    await refresh()
    show({ message: 'Profile updated successfully', type: 'success' })
  } catch {
    show({ message: 'Failed to update profile. Please try again.', type: 'error' })
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="profile-page">
    <h1 class="profile-page__title">Edit Profile</h1>

    <div class="profile-page__content">
      <ProfileForm
        v-if="profileData?.data"
        :profile="profileData.data"
        :saving="saving"
        @save="handleSave"
      />
    </div>
  </div>
</template>

<style scoped>
.profile-page__title {
  font-family: var(--font-display);
  font-size: var(--type-headline-lg);
  line-height: 1.2;
  letter-spacing: -0.02em;
  color: var(--on-surface);
  margin-bottom: var(--space-xl);
}

.profile-page__content {
  max-width: 40rem;
  margin-inline: auto;
}
</style>
