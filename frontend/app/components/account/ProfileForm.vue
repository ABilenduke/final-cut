<script setup lang="ts">
import type { UserProfile } from '~/types/user'

export interface ProfileUpdatePayload {
  name?: string
  email?: string
  phone?: string | null
  date_of_birth?: string | null
  current_password?: string
  password?: string
  password_confirmation?: string
}

const props = defineProps<{
  profile: UserProfile
  saving: boolean
}>()

const emit = defineEmits<{
  save: [data: ProfileUpdatePayload]
}>()

const name = ref(props.profile.name)
const email = ref(props.profile.email)
const phone = ref(props.profile.phone ?? '')
const dateOfBirth = ref(props.profile.dateOfBirth ?? '')
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const passwordError = ref('')
const currentPasswordError = ref('')

watch(
  () => props.profile,
  (profile) => {
    name.value = profile.name
    email.value = profile.email
    phone.value = profile.phone ?? ''
    dateOfBirth.value = profile.dateOfBirth ?? ''
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    passwordError.value = ''
    currentPasswordError.value = ''
  },
)

const avatarInitial = computed(() => {
  return props.profile.name.charAt(0).toUpperCase()
})

function handleSubmit() {
  passwordError.value = ''
  currentPasswordError.value = ''

  if (newPassword.value && !currentPassword.value) {
    currentPasswordError.value = 'Current password is required to set a new password'
    return
  }

  if (newPassword.value && newPassword.value !== confirmPassword.value) {
    passwordError.value = 'Passwords do not match'
    return
  }

  const changes: ProfileUpdatePayload = {}

  if (name.value !== props.profile.name) changes.name = name.value
  if (email.value !== props.profile.email) changes.email = email.value
  if (phone.value !== (props.profile.phone ?? '')) changes.phone = phone.value || null
  if (dateOfBirth.value !== (props.profile.dateOfBirth ?? '')) changes.date_of_birth = dateOfBirth.value || null

  if (newPassword.value) {
    changes.current_password = currentPassword.value
    changes.password = newPassword.value
    changes.password_confirmation = confirmPassword.value
  }

  if (Object.keys(changes).length === 0) return

  emit('save', changes)
}
</script>

<template>
  <form class="profile-form" @submit.prevent="handleSubmit">
    <div class="profile-form__avatar">
      <div class="profile-form__avatar-circle">
        {{ avatarInitial }}
      </div>
    </div>

    <div class="profile-form__fields">
      <CvInput
        v-model="name"
        label="Name"
        required
      />
      <CvInput
        v-model="email"
        type="email"
        label="Email"
        required
      />
      <CvInput
        v-model="phone"
        type="tel"
        label="Phone"
        placeholder="Optional"
      />
      <CvInput
        v-model="dateOfBirth"
        type="date"
        label="Date of Birth"
        placeholder="Optional"
      />
    </div>

    <div class="profile-form__password-section">
      <h3 class="profile-form__section-title headline-sm">Change Password</h3>
      <div class="profile-form__fields">
        <CvInput
          v-model="currentPassword"
          type="password"
          label="Current Password"
          :error="currentPasswordError"
        />
        <CvInput
          v-model="newPassword"
          type="password"
          label="New Password"
        />
        <CvInput
          v-model="confirmPassword"
          type="password"
          label="Confirm New Password"
          :error="passwordError"
        />
      </div>
    </div>

    <div class="profile-form__actions">
      <CvButton
        type="submit"
        :loading="saving"
        :disabled="saving"
      >
        Save Changes
      </CvButton>
    </div>
  </form>
</template>

<style scoped>
.profile-form {
  display: flex;
  flex-direction: column;
  gap: var(--space-xl);
}

.profile-form__avatar {
  display: flex;
  justify-content: center;
}

.profile-form__avatar-circle {
  width: 5rem;
  height: 5rem;
  border-radius: 50%;
  background-color: var(--primary-container);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-size: var(--type-headline-sm);
  user-select: none;
}

.profile-form__fields {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.profile-form__password-section {
  display: flex;
  flex-direction: column;
  gap: var(--space-md);
}

.profile-form__section-title {
  color: var(--on-surface);
  margin: 0;
}

.profile-form__actions {
  display: flex;
  justify-content: flex-end;
}
</style>
