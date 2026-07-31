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
      <v-btn variant="text" @click="router.back()">Отмена</v-btn>
      <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
    </div>
  </v-container>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSpaceStore } from '../Store/spaces'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import type { Space } from '../Interface/types'

const route = useRoute()
const router = useRouter()
const store = useSpaceStore()
const { signal } = useAbortable()

const space = ref<Space | null>(null)
const name = ref('')
const description = ref('')
const saving = ref(false)

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
</script>
