<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useMechanicEdit } from '@/modules/Roleplay/Mechanic/Composables/useMechanicEdit';

const router = useRouter();
const { isEdit, code, version, name, description, loading, loadError, saving, saveError, load, save } =
  useMechanicEdit();

onMounted(load);
</script>

<template>
  <v-container>
    <div v-if="!loading && !loadError">
      <h1 class="text-h5 mb-4">{{ isEdit ? 'Редактирование механики' : 'Создание механики' }}</h1>

      <v-alert v-if="saveError" type="error" class="mb-4" closable @click:close="saveError = null">
        {{ saveError }}
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
            hint="Семейство механики, например: roll, six_one_rule. Новая версия — та же код, другая версия."
            persistent-hint
          />

          <v-text-field
            v-model="version"
            label="Версия"
            :rules="[(v) => !!v || 'Обязательное поле']"
            :disabled="isEdit"
            hint="Поставка контракта. На уже созданной строке не меняется."
            persistent-hint
            class="mt-4"
          />

          <v-text-field v-model="name" label="Название" :rules="[(v) => !!v || 'Обязательное поле']" class="mt-4" />

          <v-textarea v-model="description" label="Описание" rows="3" class="mt-4" />
        </v-card-text>

        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="router.back()">Отмена</v-btn>
          <v-btn color="primary" :loading="saving" @click="save">Сохранить</v-btn>
        </v-card-actions>
      </v-card>
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
