<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useMechanicStore } from '@/modules/Roleplay/Rule/Store/mechanics';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';

const route = useRoute();
const router = useRouter();
const store = useMechanicStore();
const { signal } = useAbortable();

const isEdit = computed(() => !!route.params.id);
const mechanicId = computed(() => Number(route.params.id));

const code = ref('');
const version = ref('');
const name = ref('');
const description = ref('');
const loading = ref(false);
const loadError = ref<string | null>(null);
const saving = ref(false);
const saveError = ref<string | null>(null);

async function loadMechanic() {
  if (!isEdit.value) return;
  loading.value = true;
  loadError.value = null;
  try {
    const mechanic = await store.fetchMechanic(mechanicId.value, signal.value);
    code.value = mechanic.code;
    version.value = mechanic.version;
    name.value = mechanic.name;
    description.value = mechanic.description;
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    loadError.value = 'Не удалось загрузить механику';
  } finally {
    loading.value = false;
  }
}

onMounted(loadMechanic);

async function save() {
  if (!code.value.trim() || !name.value.trim() || !version.value.trim()) return;
  saving.value = true;
  saveError.value = null;
  try {
    if (isEdit.value) {
      await store.updateMechanic(
        mechanicId.value,
        {
          name: name.value,
          description: description.value,
        },
        signal.value,
      );
    } else {
      await store.createMechanic(
        {
          code: code.value,
          name: name.value,
          version: version.value,
          description: description.value,
        },
        signal.value,
      );
    }
    router.push('/admin/mechanics');
  } catch (e) {
    if (e instanceof DOMException && e.name === 'AbortError') return;
    saveError.value = 'Не удалось сохранить механику';
  } finally {
    saving.value = false;
  }
}
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
      <v-btn color="primary" @click="loadMechanic">Попробовать снова</v-btn>
    </div>
  </v-container>
</template>
