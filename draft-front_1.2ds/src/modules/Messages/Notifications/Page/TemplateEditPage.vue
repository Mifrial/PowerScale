<template>
  <v-container>
    <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование шаблона' : 'Создание шаблона' }}</h1>

    <v-card>
      <v-card-text>
        <v-text-field
          v-model="key"
          label="Ключ шаблона"
          :rules="[v => !!v || 'Обязательное поле', v => /^[a-z0-9_]+$/.test(v) || 'Только латиница, цифры и подчёркивание']"
          :disabled="isEdit"
          hint="Уникальный идентификатор, например: game_invite, character_moderation"
          persistent-hint
        />

        <v-text-field
          v-model="titleTemplate"
          label="Шаблон заголовка"
          :rules="[v => !!v || 'Обязательное поле']"
          class="mt-4"
          hint="Поддерживает плейсхолдеры: {{game_name}}, {{character_name}}"
          persistent-hint
        />

        <v-textarea
          v-model="bodyTemplate"
          label="Шаблон содержимого (HTML)"
          :rules="[v => !!v || 'Обязательное поле']"
          rows="5"
          class="mt-4"
          hint="Поддерживает HTML и плейсхолдеры"
          persistent-hint
        />

        <v-divider class="my-6" />

        <div class="d-flex align-center mb-3">
          <h3 class="text-h6">Кнопки действий</h3>
          <v-spacer />
          <v-btn size="small" prepend-icon="mdi-plus" @click="addButton">Добавить кнопку</v-btn>
        </div>

        <v-card v-for="(btn, idx) in buttons" :key="idx" variant="outlined" class="mb-3">
          <v-card-text>
            <v-row>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model="btn.label"
                  label="Текст кнопки"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-select
                  v-model="btn.actionType"
                  :items="actionTypes"
                  label="Тип действия"
                  density="compact"
                />
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field
                  v-model="btn.action"
                  label="Действие / URL"
                  density="compact"
                />
              </v-col>
            </v-row>
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn size="small" color="error" variant="text" @click="removeButton(idx)">Удалить</v-btn>
          </v-card-actions>
        </v-card>

        <div v-if="buttons.length === 0" class="text-body-2 text-medium-emphasis text-center py-4">
          Нет кнопок действий
        </div>
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
import { useTemplateStore } from '../Store/templates'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import type { NotificationButton } from '../Interface/types'

const route = useRoute()
const router = useRouter()
const store = useTemplateStore()
const { signal } = useAbortable()

const isEdit = computed(() => !!route.params.id)
const templateId = computed(() => Number(route.params.id))

const key = ref('')
const titleTemplate = ref('')
const bodyTemplate = ref('')
const buttons = ref<NotificationButton[]>([])
const saving = ref(false)

const actionTypes = [
  { title: 'Событие', value: 'event' },
  { title: 'URL', value: 'url' },
  { title: 'Действие', value: 'action' },
]

onMounted(async () => {
  if (isEdit.value) {
    const template = await store.fetchTemplate(templateId.value, signal.value)
    key.value = template.key
    titleTemplate.value = template.titleTemplate
    bodyTemplate.value = template.bodyTemplate
    buttons.value = template.buttonsJson ? [...template.buttonsJson] : []
  }
})

function addButton() {
  buttons.value.push({
    label: '',
    actionType: 'event',
    action: '',
    payload: {},
  })
}

function removeButton(idx: number) {
  buttons.value.splice(idx, 1)
}

async function save() {
  if (!key.value.trim() || !titleTemplate.value.trim() || !bodyTemplate.value.trim()) return
  saving.value = true
  try {
    const data = {
      key: key.value,
      titleTemplate: titleTemplate.value,
      bodyTemplate: bodyTemplate.value,
      buttonsJson: buttons.value.length > 0 ? buttons.value : undefined,
    }
    if (isEdit.value) {
      await store.updateTemplate(templateId.value, data, signal.value)
    } else {
      await store.createTemplate(data, signal.value)
    }
    router.push('/admin/notification-templates')
  } catch (e) {
    console.error('save template failed', e)
  } finally {
    saving.value = false
  }
}
</script>
