<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import type { User } from '@/modules/Core/User/Dto/User'
import { accessService } from '@/modules/Core/User/Service/AccessService'
import { getProfileSections } from '@/modules/Core/User/init'
import ProfileInfoCard from '@/modules/Core/User/Component/ProfileInfoCard.vue'
import ProfileAuthCard from '@/modules/Core/User/Component/ProfileAuthCard.vue'
import ProfileGroupsCard from '@/modules/Core/User/Component/ProfileGroupsCard.vue'
import DeactivateUserDialog from '@/modules/Core/User/Component/DeactivateUserDialog.vue'
import { initials as userInitials, displayName as userDisplayName } from '@/modules/Core/User/Utils/profile'

const profileSections = getProfileSections()

const route = useRoute()
const store = useUserStore()
const { signal } = useAbortable()

const user = ref<User | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const confirmDeactivate = ref(false)
const deactivating = ref(false)
const editing = ref(false)
const saving = ref(false)
const snackbar = ref({ show: false, text: '', color: '' })
const editForm = ref({ name: '', surname: '', nickname: '', email: '' })

const initials = computed(() => {
  if (!user.value) return '??'
  return userInitials(user.value.name, user.value.surname)
})

const displayName = computed(() => {
  if (!user.value) return ''
  return userDisplayName(user.value.name, user.value.surname, user.value.login)
})

const isOwnProfile = computed(() => store.currentUser?.id === user.value?.id)
const canEdit = computed(() => isOwnProfile.value || accessService.hasAnyPermission(store.currentUser, ['user.edit']))
const canDeactivate = computed(() => accessService.hasAnyPermission(store.currentUser, ['user.deactivate']))

function startEdit() {
  if (!user.value) return
  editForm.value = {
    name: user.value.name,
    surname: user.value.surname ?? '',
    nickname: user.value.nickname ?? '',
    email: user.value.email,
  }
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

async function save() {
  if (!user.value) return
  saving.value = true
  try {
    const data: Record<string, unknown> = {}
    for (const [k, v] of Object.entries(editForm.value)) {
      data[k] = v || null
    }
    await store.updateUser(user.value.id, data)
    Object.assign(user.value, data)
    editing.value = false
    snackbar.value = { show: true, text: 'Профиль обновлён', color: 'success' }
  } catch {
    snackbar.value = { show: true, text: 'Ошибка сохранения', color: 'error' }
  } finally {
    saving.value = false
  }
}

async function handleDeactivate(reason?: string, until?: string) {
  deactivating.value = true
  try {
    await store.deactivateUser(Number(route.params.id), reason, until)
    if (user.value) {
      user.value.active = false
      user.value.deactivate_reason = reason
      user.value.deactivated_until = until
    }
    const parts = ['Пользователь отключён']
    if (reason) parts.push(`: ${reason}`)
    if (until) parts.push(` до ${until}`)
    snackbar.value = { show: true, text: parts.join(''), color: 'success' }
  } catch {
    snackbar.value = { show: true, text: 'Ошибка', color: 'error' }
  } finally {
    deactivating.value = false
    confirmDeactivate.value = false
  }
}

async function loadUser() {
  loading.value = true
  error.value = null
  try {
    user.value = await store.getUser(Number(route.params.id), signal.value)
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return
    error.value = e instanceof Error ? e.message : 'Ошибка загрузки пользователя'
  } finally {
    loading.value = false
  }
}

onMounted(loadUser)
</script>

<template>
  <div v-if="user">
    <div class="d-flex align-center mb-6 ga-3">
      <v-btn icon variant="text" @click="$router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
      <h1 class="text-h4 font-weight-bold">{{ displayName }}</h1>
      <v-spacer />
      <v-btn v-if="user.active && canDeactivate" variant="tonal" color="error" @click="confirmDeactivate = true">Отключить</v-btn>
    </div>

    <div class="d-flex ga-6">
      <div class="profile-side text-center">
        <v-avatar color="primary" size="120" class="mb-2">
          <span class="text-h3 font-weight-medium text-white">{{ initials }}</span>
        </v-avatar>
        <div class="text-h6 font-weight-medium text-center">{{ displayName }}</div>
        <div class="text-caption text-medium-emphasis text-center">@{{ user.login }}</div>
      </div>

      <div class="profile-main">
        <div class="profile-cards-grid">
          <ProfileInfoCard
            :user="user"
            :can-edit="canEdit"
            :editing="editing"
            :saving="saving"
            v-model:form-name="editForm.name"
            v-model:form-surname="editForm.surname"
            v-model:form-nickname="editForm.nickname"
            class="profile-card"
            @start="startEdit"
            @save="save"
            @cancel="cancelEdit"
          />
          <ProfileAuthCard
            :user="user"
            :editing="editing"
            v-model:form-email="editForm.email"
            class="profile-card"
          />
          <ProfileGroupsCard :user="user" class="profile-card profile-card--full" />
        </div>
      </div>
    </div>

    <div v-if="isOwnProfile" class="mt-4">
      <component
        v-for="section in profileSections"
        :key="section.id"
        :is="section.component"
        class="mt-4"
      />
    </div>

    <DeactivateUserDialog
      v-model:open="confirmDeactivate"
      :user="user"
      :loading="deactivating"
      @confirm="handleDeactivate"
    />

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </v-snackbar>
  </div>
  <div v-else-if="loading" class="d-flex justify-center pa-8">
    <v-progress-circular indeterminate width="2" size="28" color="primary" />
  </div>
  <div v-else-if="error" class="text-center pa-8">
    <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
    <p class="text-body-1 mb-4">{{ error }}</p>
    <v-btn color="primary" @click="loadUser">Попробовать снова</v-btn>
  </div>
</template>

<style scoped>
.profile-side {
  width: 180px;
  flex-shrink: 0;
}
.profile-main {
  flex: 1;
  min-width: 0;
}
.profile-cards-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 16px;
}
.profile-card {
  flex: 1 1 320px;
  min-width: 280px;
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.profile-card--full {
  flex-basis: 100%;
}
</style>
