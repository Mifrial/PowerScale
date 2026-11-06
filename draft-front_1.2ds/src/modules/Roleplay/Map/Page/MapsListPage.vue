<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import { useMapList } from '@/modules/Roleplay/Map/Composables/useMapList';
import type { MapSummary } from '@/modules/Roleplay/Map/Dto/MapSummary';
import type { MapMeta } from '@/modules/Roleplay/Map/Dto/MapMeta';
import MapCreateDialog from '@/modules/Roleplay/Map/Component/Map/MapCreateDialog.vue';
import { MAP_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Map/Constant/Map/MAP_VISIBILITY_OPTIONS';

const router = useRouter();
const { currentUser } = useCurrentUser();
const {
  items,
  page,
  pageCount,
  searchQuery,
  isArchived,
  loading,
  error,
  load,
  createMap,
  archiveMap: archiveMapRequest,
  setPage,
  setSearch,
  setArchived,
} = useMapList();

const canCreate = computed(() => accessService.hasAnyPermission(currentUser.value, ['map.edit']));
const createOpen = ref(false);
const creating = ref(false);
const createError = ref<string | null>(null);
const archiveTarget = ref<MapSummary | null>(null);

const headers = [
  { title: 'Название', key: 'name' },
  { title: 'Видимость', key: 'visibility', width: 140 },
  { title: 'Обновлена', key: 'updatedAt', width: 190 },
];

const visibilityLabel = (value: string): string =>
  MAP_VISIBILITY_OPTIONS.find((option) => option.value === value)?.title ?? value;

const formatDate = (unixSeconds: number): string => new Date(unixSeconds * 1000).toLocaleString('ru-RU');

function openMap(map: MapSummary): void {
  void router.push({ name: 'MapDetail', params: { id: map.id } });
}

function applySearch(): void {
  setSearch(searchQuery.value);
  void load();
}

async function submitCreate(meta: MapMeta): Promise<void> {
  creating.value = true;
  createError.value = null;
  try {
    const created = await createMap(meta);
    createOpen.value = false;
    openMap(created);
  } catch (caught) {
    createError.value = caught instanceof Error ? caught.message : 'Не удалось создать карту';
  } finally {
    creating.value = false;
  }
}

function archiveMap(): void {
  const target = archiveTarget.value;
  if (!target) return;
  archiveTarget.value = null;
  void archiveMapRequest(target.id);
}

onMounted(load);
</script>

<template>
  <v-container fluid>
    <div class="d-flex flex-wrap align-center ga-3 mb-4">
      <h1 class="text-h6">Карты</h1>
      <v-spacer />
      <v-text-field
        v-model="searchQuery"
        density="compact"
        variant="outlined"
        label="Поиск"
        prepend-inner-icon="mdi-magnify"
        hide-details
        clearable
        style="max-width: 280px"
        @keyup.enter="applySearch"
        @click:clear="applySearch"
      />
      <v-switch
        :model-value="isArchived"
        label="Архив"
        color="primary"
        density="compact"
        hide-details
        @update:model-value="setArchived($event === true)"
      />
      <v-btn v-if="canCreate" color="primary" variant="tonal" prepend-icon="mdi-plus" @click="createOpen = true">
        Новая карта
      </v-btn>
    </div>

    <v-alert v-if="error" type="error" variant="tonal" class="mb-3">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="load">Повторить</v-btn>
      </template>
    </v-alert>

    <v-table hover>
      <thead>
        <tr>
          <th
            v-for="header in headers"
            :key="header.key"
            :style="{ width: header.width ? `${header.width}px` : undefined }"
          >
            {{ header.title }}
          </th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-for="map in items" :key="map.id">
          <td>
            <a class="text-primary" href="#" @click.prevent="openMap(map)">{{ map.name }}</a>
            <div class="text-caption text-medium-emphasis">{{ map.description }}</div>
          </td>
          <td>{{ visibilityLabel(map.visibility) }}</td>
          <td>{{ formatDate(map.updatedAt) }}</td>
          <td class="text-right">
            <v-btn size="small" variant="text" @click="openMap(map)">Открыть</v-btn>
            <v-btn
              v-if="canCreate && !map.isArchived"
              size="small"
              variant="text"
              color="error"
              @click="archiveTarget = map"
            >
              В архив
            </v-btn>
          </td>
        </tr>
        <tr v-if="!loading && items.length === 0">
          <td :colspan="headers.length + 1" class="text-center text-medium-emphasis py-6">Карт пока нет</td>
        </tr>
      </tbody>
    </v-table>

    <div class="d-flex justify-center mt-4">
      <v-pagination
        v-model="page"
        :length="pageCount"
        :total-visible="7"
        density="comfortable"
        @update:model-value="setPage($event)"
      />
    </div>

    <MapCreateDialog v-model:open="createOpen" :submitting="creating" @submit="submitCreate" />

    <v-dialog :model-value="archiveTarget !== null" max-width="420" @update:model-value="archiveTarget = null">
      <v-card>
        <v-card-title class="text-body-1 font-weight-medium">В архив</v-card-title>
        <v-card-text>Карта «{{ archiveTarget?.name }}» станет недоступна в общем списке.</v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="archiveTarget = null">Отмена</v-btn>
          <v-btn color="error" variant="tonal" @click="archiveMap">В архив</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>
