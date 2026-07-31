<template>
  <v-container>
    <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование тега' : 'Создание тега' }}</h1>

    <v-card>
      <v-card-text>
        <v-text-field
          v-model="code"
          label="Код (code)"
          :rules="[v => !!v || 'Обязательное поле', v => /^[a-z0-9_]+$/.test(v) || 'Только латиница, цифры и подчёркивание']"
          :disabled="isEdit"
          hint="Уникальный идентификатор, например: melee, magic, stealth"
          persistent-hint
        />

        <v-text-field
          v-model="name"
          label="Название"
          :rules="[v => !!v || 'Обязательное поле']"
          class="mt-4"
        />

        <v-textarea
          v-model="description"
          label="Описание"
          rows="3"
          class="mt-4"
        />
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useTagStore } from '../Store/tags'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'

const route = useRoute()
const router = useRouter()
const store = useTagStore()
const { signal } = useAbortable()

const isEdit = computed(() => !!route.params.id)
const tagId = computed(() => Number(route.params.id))

const code = ref('')
const name = ref('')
const description = ref('')
const saving = ref(false)

onMounted(async () => {
  if (isEdit.value) {
    const tag = await store.fetchTag(tagId.value, signal.value)
    code.value = tag.code
    name.value = tag.name
    description.value = tag.description ?? ''
  }
})

async function save() {
  if (!code.value.trim() || !name.value.trim()) return
  saving.value = true
  try {
    if (isEdit.value) {
      await store.updateTag(tagId.value, {
        name: name.value,
        description: description.value || undefined,
      }, signal.value)
    } else {
      await store.createTag({
        code: code.value,
        name: name.value,
        description: description.value || undefined,
      }, signal.value)
    }
    router.push('/admin/tags')
  } catch (e) {
    console.error('save tag failed', e)
  } finally {
    saving.value = false
  }
}
</script>
