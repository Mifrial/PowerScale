<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { accessService } from '@/modules/Core/User/init'

const route = useRoute()
const router = useRouter()
const store = useKeywordStore()
const userStore = useUserStore()
const { signal } = useAbortable()

const isEdit = computed(() => !!route.params.id)
const tagId = computed(() => Number(route.params.id))

const code = ref('')
const name = ref('')
const description = ref('')
const active = ref(true)
const saving = ref(false)
const showDeleteDialog = ref(false)
const deleting = ref(false)

const canDelete = computed(() => accessService.hasAnyPermission(userStore.currentUser, ['keyword.delete']))

onMounted(async () => {
  if (isEdit.value) {
    const keyword = await store.fetchTag(tagId.value, signal.value)
    code.value = keyword.code
    name.value = keyword.name
    description.value = keyword.description ?? ''
    active.value = keyword.active
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
    router.push('/admin/keywords')
  } catch (e) {
    console.error('save keyword failed', e)
  } finally {
    saving.value = false
  }
}

async function handleDelete() {
  deleting.value = true
  try {
    await store.deactivateTag(tagId.value, signal.value)
    router.push('/admin/keywords')
  } catch (e) {
    console.error('delete keyword failed', e)
  } finally {
    deleting.value = false
  }
}
</script>

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
        <v-btn
          v-if="isEdit && canDelete && active"
          variant="text"
          color="error"
          prepend-icon="mdi-trash-can-outline"
          @click="showDeleteDialog = true"
        >
          Удалить
        </v-btn>
        <v-spacer />
        <v-btn variant="text" @click="router.back()">Отмена</v-btn>
        <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
      </v-card-actions>
    </v-card>

    <v-dialog v-model="showDeleteDialog" max-width="400">
      <v-card>
        <v-card-title>Удалить тег?</v-card-title>
        <v-card-text>
          Тег «{{ name }}» будет деактивирован (soft-delete): скроется из выбора, старые связи сохранятся.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showDeleteDialog = false">Отмена</v-btn>
          <v-btn color="error" :loading="deleting" @click="handleDelete">Удалить</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
