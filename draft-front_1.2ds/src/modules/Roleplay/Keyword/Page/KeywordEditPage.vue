<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import { useKeywordEdit } from '@/modules/Roleplay/Keyword/Composables/useKeywordEdit';

const router = useRouter();
const { currentUser } = useCurrentUser();
const showDeleteDialog = ref(false);
const {
  isEdit,
  code,
  name,
  description,
  active,
  loading,
  loadError,
  saving,
  saveError,
  deactivating,
  actionError,
  load,
  save,
  deactivate,
} = useKeywordEdit();

const canDelete = computed(() => accessService.hasAnyPermission(currentUser.value, ['keyword.delete']));

onMounted(load);
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
              (v) => /^[a-z0-9_-]+$/.test(v) || 'Только латиница, цифры, подчёркивание и дефис',
            ]"
            :disabled="isEdit"
            hint="Уникальный идентификатор, например: melee, wood-elf, item-section-armor"
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
            Выключить
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="router.back()">Отмена</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
        </v-card-actions>
      </v-card>

      <v-dialog v-model="showDeleteDialog" max-width="400">
        <v-card>
          <v-card-title>Выключить признак?</v-card-title>
          <v-card-text> Признак «{{ name }}» будет выключен: скроется из выбора, старые связи сохранятся. </v-card-text>
          <v-card-actions>
            <v-spacer />
            <v-btn variant="text" @click="showDeleteDialog = false">Отмена</v-btn>
            <v-btn color="error" :loading="deactivating" @click="deactivate">Выключить</v-btn>
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
      <v-btn color="primary" @click="load">Попробовать снова</v-btn>
    </div>
  </v-container>
</template>
