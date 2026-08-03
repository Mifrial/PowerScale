<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { accessService } from '@/modules/Core/User/init'
import type { Space } from '@/modules/Roleplay/Space/Dto/Space'

const route = useRoute()
const router = useRouter()
const store = useSpaceStore()
const userStore = useUserStore()
const { signal } = useAbortable()

const space = ref<Space | null>(null)
const name = ref('')
const description = ref('')
const saving = ref(false)
const showDeactivateDialog = ref(false)
const deactivating = ref(false)

const canDeactivate = computed(() => accessService.hasAnyPermission(userStore.currentUser, ['space.edit_all']))

onMounted(async () => {
  const code = route.params.code as string
  await store.fetchSpaceByCode(code, signal.value)
  space.value = store.currentSpace
  if (space.value) {
    name.value = space.value.name
    description.value = space.value.description
  }
})

async function save() {
  if (!name.value.trim() || !space.value) return
  saving.value = true
  try {
    await store.updateSpace(space.value.id, {
      name: name.value,
      description: description.value,
    }, signal.value)
    router.push(`/space/${space.value.code}`)
  } catch (e) {
    console.error('update space failed', e)
  } finally {
    saving.value = false
  }
}

async function deactivate() {
  if (!space.value) return
  deactivating.value = true
  try {
    await store.deactivateSpace(space.value.id, signal.value)
    router.push('/spaces')
  } catch (e) {
    console.error('deactivate space failed', e)
  } finally {
    deactivating.value = false
  }
}
</script>

<template>
  <v-container v-if="space">
    <h1 class="text-h5 mb-4">Настройки: {{ space.name }}</h1>

    <v-card class="mb-4">
      <v-card-title>Основные настройки</v-card-title>
      <v-card-text>
        <v-text-field
          v-model="name"
          label="Название"
          :rules="[v => !!v || 'Обязательное поле']"
        />

        <v-textarea
          v-model="description"
          label="Описание"
          rows="3"
          class="mt-4"
        />

        <v-text-field
          :model-value="space.revision"
          label="Версия"
          disabled
          hint="Автоинкремент при каждом коммите правил"
          persistent-hint
          class="mt-4"
        />
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title>Модераторы</v-card-title>
      <v-card-text>
        <div class="text-body-2 text-medium-emphasis">
          Управление модераторами будет реализовано позже
        </div>
      </v-card-text>
    </v-card>

    <v-card class="mb-4">
      <v-card-title>Права доступа</v-card-title>
      <v-card-text>
        <div class="text-body-2 text-medium-emphasis">
          Управление правами доступа будет реализовано позже
        </div>
      </v-card-text>
    </v-card>

    <div class="d-flex justify-end ga-2">
      <v-btn
        v-if="canDeactivate"
        variant="text"
        color="error"
        prepend-icon="mdi-cancel"
        class="mr-auto"
        @click="showDeactivateDialog = true"
      >
        Деактивировать
      </v-btn>
      <v-btn variant="text" @click="router.back()">Отмена</v-btn>
      <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
    </div>

    <v-dialog v-model="showDeactivateDialog" max-width="400">
      <v-card>
        <v-card-title>Деактивировать пространство?</v-card-title>
        <v-card-text>
          Пространство «{{ space.name }}» будет скрыто из списков, данные сохранятся.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeactivateDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="deactivating" @click="deactivate">Деактивировать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
