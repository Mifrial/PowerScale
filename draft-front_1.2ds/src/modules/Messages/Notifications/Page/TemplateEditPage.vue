<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useTemplateStore } from '@/modules/Messages/Notifications/Store/templates';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { accessService } from '@/modules/Core/User/init';
import type { NotificationButton } from '@/modules/Messages/Notifications/Dto/NotificationButton';
import { actionTypes } from '@/modules/Messages/Notifications/Constant/templateActionTypes';

const route = useRoute();
const router = useRouter();
const store = useTemplateStore();
const userStore = useUserStore();
const { signal } = useAbortable();

const isEdit = computed(() => !!route.params.id);
const templateId = computed(() => Number(route.params.id));

const key = ref('');
const titleTemplate = ref('');
const bodyTemplate = ref('');
const buttons = ref<NotificationButton[]>([]);
const active = ref(true);
const saving = ref(false);
const showDeleteDialog = ref(false);
const deleting = ref(false);

const canDelete = computed(() =>
  accessService.hasAnyPermission(userStore.currentUser, ['notification_template.delete']),
);

onMounted(async () => {
  if (isEdit.value) {
    const template = await store.fetchTemplate(templateId.value, signal.value);
    key.value = template.key;
    titleTemplate.value = template.titleTemplate;
    bodyTemplate.value = template.bodyTemplate;
    buttons.value = template.buttonsJson ? [...template.buttonsJson] : [];
    active.value = template.active;
  }
});

function addButton() {
  buttons.value.push({
    label: '',
    actionType: 'event',
    action: '',
    payload: {},
  });
}

function removeButton(idx: number) {
  buttons.value.splice(idx, 1);
}

async function save() {
  if (!key.value.trim() || !titleTemplate.value.trim() || !bodyTemplate.value.trim()) return;
  saving.value = true;
  try {
    const data = {
      key: key.value,
      titleTemplate: titleTemplate.value,
      bodyTemplate: bodyTemplate.value,
      buttonsJson: buttons.value.length > 0 ? buttons.value : undefined,
    };
    if (isEdit.value) {
      await store.updateTemplate(templateId.value, data, signal.value);
    } else {
      await store.createTemplate(data, signal.value);
    }
    router.push('/admin/notification-templates');
  } catch (e) {
    console.error('save template failed', e);
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  deleting.value = true;
  try {
    await store.deactivateTemplate(templateId.value, signal.value);
    router.push('/admin/notification-templates');
  } catch (e) {
    console.error('delete template failed', e);
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование шаблона' : 'Создание шаблона' }}</h1>

    <v-card>
      <v-card-text>
        <v-text-field
          v-model="key"
          label="Ключ шаблона"
          :rules="[
            (v) => !!v || 'Обязательное поле',
            (v) => /^[a-z0-9_]+$/.test(v) || 'Только латиница, цифры и подчёркивание',
          ]"
          :disabled="isEdit"
          hint="Уникальный идентификатор, например: game_invite, character_moderation"
          persistent-hint
        />

        <v-text-field
          v-model="titleTemplate"
          label="Шаблон заголовка"
          :rules="[(v) => !!v || 'Обязательное поле']"
          class="mt-4"
          hint="Поддерживает плейсхолдеры: {{game_name}}, {{character_name}}"
          persistent-hint
        />

        <v-textarea
          v-model="bodyTemplate"
          label="Шаблон содержимого (HTML)"
          :rules="[(v) => !!v || 'Обязательное поле']"
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
                <v-text-field v-model="btn.label" label="Текст кнопки" density="compact" />
              </v-col>
              <v-col cols="12" md="4">
                <v-select v-model="btn.actionType" :items="actionTypes" label="Тип действия" density="compact" />
              </v-col>
              <v-col cols="12" md="4">
                <v-text-field v-model="btn.action" label="Действие / URL" density="compact" />
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
        <v-card-title>Удалить шаблон?</v-card-title>
        <v-card-text>
          Шаблон «{{ key }}» будет деактивирован (soft-delete): скроется из списка активных, старые уведомления
          сохранятся.
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
