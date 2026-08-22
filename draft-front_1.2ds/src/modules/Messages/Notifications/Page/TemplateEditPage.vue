<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { TemplateForm } from '@/modules/Messages/Notifications/Dto/TemplateForm';
import { useTemplateStore } from '@/modules/Messages/Notifications/Store/templates';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { accessService } from '@/modules/Core/User/init';
import { templateSpecService } from '@/modules/Messages/Notifications/Service/Instance/templateSpecService';
import TemplateButtonsEditor from '@/modules/Messages/Notifications/Component/TemplateButtonsEditor.vue';

const route = useRoute();
const router = useRouter();
const store = useTemplateStore();
const userStore = useUserStore();
const { signal } = useAbortable();

const isEdit = computed(() => !!route.params.id);
const templateId = computed(() => Number(route.params.id));

const form = reactive<TemplateForm>(templateSpecService.createEmpty());
const formRef = ref<{ validate: () => Promise<{ valid: boolean }> }>();
const saving = ref(false);
const deleting = ref(false);
const showDeleteDialog = ref(false);
const errorMessage = ref('');

const canDelete = computed(() =>
  accessService.hasAnyPermission(userStore.currentUser, ['notification_template.delete']),
);

onMounted(async () => {
  if (!isEdit.value) return;
  try {
    const template = await store.fetchTemplate(templateId.value, signal.value);
    templateSpecService.fill(form, template);
  } catch (e) {
    if (!isAbortError(e)) errorMessage.value = 'Не удалось загрузить шаблон';
  }
});

function isAbortError(e: unknown): boolean {
  return e instanceof DOMException && e.name === 'AbortError';
}

async function save() {
  if (formRef.value && !(await formRef.value.validate()).valid) return;
  saving.value = true;
  errorMessage.value = '';
  try {
    if (isEdit.value) {
      await store.updateTemplate(templateId.value, templateSpecService.buildUpdatePayload(form), signal.value);
    } else {
      await store.createTemplate(templateSpecService.buildCreatePayload(form), signal.value);
    }
    router.push('/admin/notification-templates');
  } catch (e) {
    if (!isAbortError(e)) errorMessage.value = 'Не удалось сохранить шаблон';
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  deleting.value = true;
  errorMessage.value = '';
  try {
    await store.deactivateTemplate(templateId.value, signal.value);
    router.push('/admin/notification-templates');
  } catch (e) {
    if (!isAbortError(e)) errorMessage.value = 'Не удалось удалить шаблон';
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование шаблона' : 'Создание шаблона' }}</h1>

    <v-alert v-if="errorMessage" type="error" class="mb-4" closable @click:close="errorMessage = ''">
      {{ errorMessage }}
    </v-alert>

    <v-card>
      <v-card-text>
        <v-form ref="formRef">
          <v-text-field
            v-model="form.key"
            label="Ключ шаблона"
            :rules="[
              (v) => !!v || 'Обязательное поле',
              (v) => !v || /^[a-z0-9_]+$/.test(v) || 'Только латиница, цифры и подчёркивание',
            ]"
            :disabled="isEdit"
            hint="Уникальный идентификатор, например: game_invite, character_moderation"
            persistent-hint
          />

          <v-text-field
            v-model="form.titleTemplate"
            label="Шаблон заголовка"
            :rules="[(v) => !!v || 'Обязательное поле']"
            class="mt-4"
            hint="Поддерживает плейсхолдеры: {{game_name}}, {{character_name}}"
            persistent-hint
          />

          <v-textarea
            v-model="form.bodyTemplate"
            label="Шаблон содержимого (HTML)"
            :rules="[(v) => !!v || 'Обязательное поле']"
            rows="5"
            class="mt-4"
            hint="Поддерживает HTML и плейсхолдеры"
            persistent-hint
          />
        </v-form>

        <v-divider class="my-6" />

        <TemplateButtonsEditor v-model:buttons="form.buttons" />
      </v-card-text>

      <v-card-actions>
        <v-btn
          v-if="isEdit && canDelete && form.active"
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
          Шаблон «{{ form.key }}» будет деактивирован (soft-delete): скроется из списка активных, старые уведомления
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
