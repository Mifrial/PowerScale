<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';

const route = useRoute();
const router = useRouter();
const store = useKeywordStore();
const { currentUser } = useCurrentUser();
const { signal } = useAbortable();

const isEdit = computed(() => !!route.params.id);
const tagId = computed(() => Number(route.params.id));

const code = ref('');
const name = ref('');
const description = ref('');
const active = ref(true);
const loading = ref(false);
const loadError = ref<string | null>(null);
const saving = ref(false);
const showDeleteDialog = ref(false);
const deleting = ref(false);
const saveError = ref<string | null>(null);
const actionError = ref<string | null>(null);

const canDelete = computed(() => accessService.hasAnyPermission(currentUser.value, ['keyword.delete']));

async function loadTag() {
  if (!isEdit.value) return;
  loading.value = true;
  loadError.value = null;
  try {
    const keyword = await store.fetchTag(tagId.value, signal.value);
    code.value = keyword.code;
    name.value = keyword.name;
    description.value = keyword.description ?? '';
    active.value = keyword.active;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadError.value = 'Не удалось загрузить признак';
  } finally {
    loading.value = false;
  }
}

onMounted(loadTag);

async function save() {
  if (!code.value.trim() || !name.value.trim()) return;
  saving.value = true;
  saveError.value = null;
  try {
    if (isEdit.value) {
      await store.updateTag(
        tagId.value,
        {
          name: name.value,
          description: description.value || undefined,
        },
        signal.value,
      );
    } else {
      await store.createTag(
        {
          code: code.value,
          name: name.value,
          description: description.value || undefined,
        },
        signal.value,
      );
    }
    router.push('/admin/keywords');
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    saveError.value = 'Не удалось сохранить признак';
  } finally {
    saving.value = false;
  }
}

async function handleDelete() {
  deleting.value = true;
  actionError.value = null;
  try {
    await store.deactivateTag(tagId.value, signal.value);
    router.push('/admin/keywords');
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    actionError.value = 'Не удалось удалить признак';
  } finally {
    deleting.value = false;
  }
}
</script>

<template>
  <v-container>
    <div v-if="!loading && !loadError">
      <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование признака' : 'Создание признака' }}</h1>

      <v-alert v-if="saveError" type="error" class="mb-4" closable @click:close="saveError = null">
        {{ saveError }}
      </v-alert>

      <v-alert v-if="actionError" type="error" class="mb-4" closable @click:close="actionError = null">
        {{ actionError }}
      </v-alert>

      <v-card>
        <v-card-text>
          <v-text-field
            v-model="code"
            label="Код (code)"
            :rules="[
              (v) => !!v || 'Обязательное поле',
              (v) => /^[a-z0-9_]+$/.test(v) || 'Только латиница, цифры и подчёркивание',
            ]"
            :disabled="isEdit"
            hint="Уникальный идентификатор, например: melee, magic, stealth"
            persistent-hint
          />

          <v-text-field v-model="name" label="Название" :rules="[(v) => !!v || 'Обязательное поле']" class="mt-4" />

          <v-textarea v-model="description" label="Описание" rows="3" class="mt-4" />
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
          <v-card-title>Удалить признак?</v-card-title>
          <v-card-text>
            Признак «{{ name }}» будет деактивирован (soft-delete): скроется из выбора, старые связи сохранятся.
          </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="showDeleteDialog = false">Отмена</v-btn>
            <v-btn color="error" :loading="deleting" @click="handleDelete">Удалить</v-btn>
          </v-card-actions>
        </v-card>
      </v-dialog>
    </div>

    <div v-else-if="loading" class="d-flex justify-center pa-8">
      <v-progress-circular indeterminate width="2" size="28" color="primary" />
    </div>
    <div v-else-if="loadError" class="text-center pa-8">
      <v-icon icon="mdi-alert-circle" size="64" color="error" class="mb-4" />
      <p class="text-body-1 mb-4">{{ loadError }}</p>
      <v-btn color="primary" @click="loadTag">Попробовать снова</v-btn>
    </div>
  </v-container>
</template>
