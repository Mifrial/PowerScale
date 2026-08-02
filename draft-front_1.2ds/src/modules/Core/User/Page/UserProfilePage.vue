<template>
  <div v-if="user">
    <div class="d-flex align-center mb-6 ga-3">
      <v-btn icon variant="text" @click="$router.back()"><v-icon>mdi-arrow-left</v-icon></v-btn>
      <h1 class="text-h4 font-weight-bold">{{ displayName }}</h1>
      <v-spacer />
      <v-btn v-if="canEdit && !editing" variant="tonal" color="primary" @click="startEdit">
        <v-icon start size="small">mdi-pencil</v-icon>Редактировать
      </v-btn>
      <v-btn v-if="canEdit && editing" variant="tonal" color="primary" :loading="saving" @click="save">
        <v-icon start size="small">mdi-check</v-icon>Сохранить
      </v-btn>
      <v-btn v-if="editing" variant="text" color="medium-emphasis" @click="cancelEdit">
        <v-icon start size="small">mdi-close</v-icon>Отмена
      </v-btn>
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
          <v-card class="profile-card">
            <v-card-item>
              <v-card-title class="text-body-1 font-weight-bold">
                <v-icon start size="small" class="mb-1">mdi-account-outline</v-icon>
                Основная информация
              </v-card-title>
            </v-card-item>
            <v-divider />
            <v-list>
              <v-list-item>
                <template #title>Имя</template>
                <template v-if="editing" #subtitle>
                  <v-text-field v-model="editForm.name" density="compact" hide-details variant="outlined" />
                </template>
                <template v-else #subtitle>{{ user.name || '—' }}</template>
              </v-list-item>

              <v-list-item>
                <template #title>Фамилия</template>
                <template v-if="editing" #subtitle>
                  <v-text-field v-model="editForm.surname" density="compact" hide-details variant="outlined" />
                </template>
                <template v-else #subtitle>{{ user.surname || '—' }}</template>
              </v-list-item>

              <v-list-item>
                <template #title>Псевдоним</template>
                <template v-if="editing" #subtitle>
                  <v-text-field v-model="editForm.nickname" density="compact" hide-details variant="outlined" />
                </template>
                <template v-else #subtitle>
                  <span v-if="user.nickname">@{{ user.nickname }}</span>
                  <span v-else class="text-medium-emphasis">—</span>
                </template>
              </v-list-item>

              <v-list-item>
                <template #title>Статус</template>
                <template #subtitle>
                  <v-chip :color="user.active ? 'success' : 'grey'" size="x-small" label>
                    {{ user.active ? 'Активен' : 'Отключён' }}
                  </v-chip>
                  <template v-if="!user.active">
                    <div v-if="user.deactivate_reason" class="text-caption mt-1">
                      Причина: {{ user.deactivate_reason }}
                    </div>
                    <div v-if="user.deactivated_until" class="text-caption">
                      Отключён до: {{ user.deactivated_until }}
                    </div>
                  </template>
                </template>
              </v-list-item>
            </v-list>
          </v-card>

          <v-card class="profile-card">
            <v-card-item>
              <v-card-title class="text-body-1 font-weight-bold">
                <v-icon start size="small" class="mb-1">mdi-shield-lock-outline</v-icon>
                Авторизация
              </v-card-title>
            </v-card-item>
            <v-divider />
            <v-list>
              <v-list-item>
                <template #title>Логин</template>
                <template #subtitle>{{ user.login }}</template>
              </v-list-item>
              <v-list-item>
                <template #title>Email</template>
                <template v-if="editing" #subtitle>
                  <v-text-field v-model="editForm.email" density="compact" hide-details variant="outlined" />
                </template>
                <template v-else #subtitle>{{ user.email }}</template>
              </v-list-item>
              <v-divider />
              <v-list-item>
                <template #title>Зарегистрирован</template>
                <template #subtitle>{{ user.registered || '—' }}</template>
              </v-list-item>
              <v-list-item>
                <template #title>Последний вход</template>
                <template #subtitle>{{ user.lastLogin || '—' }}</template>
              </v-list-item>
            </v-list>
          </v-card>

          <v-card class="profile-card profile-card--full">
            <v-card-item>
              <v-card-title class="text-body-1 font-weight-bold">
                <v-icon start size="small" class="mb-1">mdi-account-group-outline</v-icon>
                Группы
              </v-card-title>
            </v-card-item>
            <v-divider />
            <v-card-text>
              <v-chip
                v-for="g in user.groups"
                :key="g"
                size="small"
                label
                class="mr-1 mb-1"
              >{{ g }}</v-chip>
              <span v-if="!user.groups.length" class="text-caption text-medium-emphasis">Нет групп</span>
            </v-card-text>
          </v-card>
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

    <v-dialog v-model="confirmDeactivate" max-width="460">
      <v-card>
        <v-card-title>Отключить пользователя</v-card-title>
        <v-card-subtitle class="text-body-2">
          Пользователь «{{ user.name }}» будет отключён
        </v-card-subtitle>
        <v-card-text>
          <v-textarea
            v-model="deactivateReason"
            label="Причина"
            placeholder="Нарушение правил, неактивность…"
            rows="2"
            variant="outlined"
            class="mb-3"
            hide-details
          />
          <v-text-field
            v-model="deactivateUntil"
            label="Отключён до (опционально)"
            type="date"
            variant="outlined"
            hide-details
          />
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="confirmDeactivate = false">Отмена</v-btn>
          <v-btn color="error" :loading="deactivating" @click="handleDeactivate">Отключить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import type { User } from '@/modules/Core/User/Dto/User'
import { accessService } from '@/modules/Core/User/Service/AccessService'
import { getProfileSections } from '@/modules/Core/User/init'

const profileSections = getProfileSections()

const route = useRoute()
const store = useUserStore()
const auth = useAuthStore()
const { signal } = useAbortable()

const user = ref<User | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const confirmDeactivate = ref(false)
const deactivating = ref(false)
const deactivateReason = ref('')
const deactivateUntil = ref('')
const editing = ref(false)
const saving = ref(false)
const snackbar = ref({ show: false, text: '', color: '' })
const editForm = ref<Record<string, string>>({})

const initials = computed(() => {
  if (!user.value) return '??'
  const first = user.value.name?.[0] || ''
  const second = user.value.surname?.[0] || ''
  return (first + second).toUpperCase() || '?'
})

const displayName = computed(() => {
  if (!user.value) return ''
  const parts = [user.value.name, user.value.surname].filter(Boolean)
  return parts.join(' ') || user.value.login
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

async function handleDeactivate() {
  deactivating.value = true
  try {
    await store.deactivateUser(
      Number(route.params.id),
      deactivateReason.value || undefined,
      deactivateUntil.value || undefined,
    )
    if (user.value) {
      user.value.active = false
      user.value.deactivate_reason = deactivateReason.value || undefined
      user.value.deactivated_until = deactivateUntil.value || undefined
    }
    const parts = ['Пользователь отключён']
    if (deactivateReason.value) parts.push(`: ${deactivateReason.value}`)
    if (deactivateUntil.value) parts.push(` до ${deactivateUntil.value}`)
    snackbar.value = { show: true, text: parts.join(''), color: 'success' }
  } catch {
    snackbar.value = { show: true, text: 'Ошибка', color: 'error' }
  } finally {
    deactivating.value = false
    confirmDeactivate.value = false
    deactivateReason.value = ''
    deactivateUntil.value = ''
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
