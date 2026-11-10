<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import type { SceneSummary } from '@/modules/Roleplay/Scene/Dto/SceneSummary';
import { getSceneApi } from '@/modules/Roleplay/Scene/init';
import { SceneApiError } from '@/modules/Roleplay/Scene/Service/SceneApiError';
import { sceneSnapshotFactory } from '@/modules/Roleplay/Scene/Service/Instance/sceneSnapshotFactory';

const router = useRouter();
const scenes = ref<SceneSummary[]>([]);
const total = ref(0);
const loading = ref(false);
const creating = ref(false);
const error = ref<string | null>(null);
const q = ref('');
const includeArchived = ref(false);
let requestId = 0;

onMounted(load);

async function load(): Promise<void> {
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const page = await getSceneApi().findPage({
      limit: 50,
      offset: 0,
      q: q.value.trim() === '' ? undefined : q.value.trim(),
      isArchived: includeArchived.value,
    });
    if (current !== requestId) return;
    scenes.value = page.items;
    total.value = page.total;
  } catch (exception) {
    if (current !== requestId) return;
    error.value = messageOf(exception);
  } finally {
    if (current === requestId) {
      loading.value = false;
    }
  }
}

async function create(): Promise<void> {
  creating.value = true;
  error.value = null;
  try {
    const detail = await getSceneApi().save({ scene: sceneSnapshotFactory.createDefaultSnapshot() });
    await router.push({ name: 'SceneDetail', params: { id: detail.id } });
  } catch (exception) {
    error.value = messageOf(exception);
  } finally {
    creating.value = false;
  }
}

function open(id: number): void {
  void router.push({ name: 'SceneDetail', params: { id } });
}

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleString();
}

function messageOf(exception: unknown): string {
  return exception instanceof SceneApiError ? exception.message : 'Не удалось загрузить локации';
}
</script>

<template>
  <v-container fluid class="pa-6">
    <div class="d-flex align-center mb-4 ga-4">
      <div>
        <h1 class="text-h5">Локации</h1>
        <div class="text-body-2 text-medium-emphasis">Авторские поля для сцен</div>
      </div>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" :loading="creating" @click="create"> Новая локация </v-btn>
    </div>

    <v-card variant="outlined">
      <v-card-text>
        <div class="d-flex align-center ga-3 mb-4 flex-wrap">
          <v-text-field
            v-model="q"
            label="Поиск"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            hide-details
            class="flex-grow-1"
            @keyup.enter="load"
          />
          <v-switch v-model="includeArchived" label="Архив" density="compact" hide-details @update:model-value="load" />
          <v-btn variant="tonal" prepend-icon="mdi-refresh" @click="load">Обновить</v-btn>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
          <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
        </v-alert>

        <v-progress-linear v-if="loading" indeterminate class="mb-4" />

        <v-table v-if="scenes.length > 0">
          <thead>
            <tr>
              <th>Название</th>
              <th>Описание</th>
              <th>Версия</th>
              <th>Обновлена</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="scene in scenes" :key="scene.id">
              <td>
                <v-btn variant="text" class="px-0" @click="open(scene.id)">{{ scene.name }}</v-btn>
                <v-chip v-if="scene.isArchived" size="x-small" class="ml-2">архив</v-chip>
              </td>
              <td class="text-medium-emphasis">{{ scene.description }}</td>
              <td>{{ scene.version }}</td>
              <td class="text-medium-emphasis">{{ formatDate(scene.updatedAt) }}</td>
              <td class="text-right">
                <v-btn size="small" variant="tonal" @click="open(scene.id)">Открыть</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>

        <v-alert v-else-if="!loading" type="info" variant="tonal"> Пока нет локаций. Создайте первую. </v-alert>

        <div v-if="total > scenes.length" class="text-caption text-medium-emphasis mt-3">
          Показано {{ scenes.length }} из {{ total }}
        </div>
      </v-card-text>
    </v-card>
  </v-container>
</template>
