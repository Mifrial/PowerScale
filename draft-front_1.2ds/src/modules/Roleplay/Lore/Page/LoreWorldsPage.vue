<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import type { LoreWorld } from '@/modules/Roleplay/Lore/Dto/LoreWorld';
import type { LoreWorldState } from '@/modules/Roleplay/Lore/Enum/LoreWorldState';
import { LORE_VISIBILITY_LABELS } from '@/modules/Roleplay/Lore/Constant/LORE_STATUS_LABELS';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';
import LoreWorldCreateDialog from '@/modules/Roleplay/Lore/Component/LoreWorldCreateDialog.vue';

const router = useRouter();
const { currentUser } = useCurrentUser();
const worlds = ref<LoreWorld[]>([]);
const total = ref(0);
const loading = ref(false);
const error = ref<string | null>(null);
const q = ref('');
const state = ref<LoreWorldState>('active');
const createOpen = ref(false);
let requestId = 0;

const canCreate = computed(() => accessService.hasAnyPermission(currentUser.value, ['lore.create']));

onMounted(load);

async function load(): Promise<void> {
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const page = await getLoreApi().findWorldPage({
      limit: 50,
      offset: 0,
      q: q.value.trim() === '' ? undefined : q.value.trim(),
      state: state.value,
    });
    if (current !== requestId) return;
    worlds.value = page.items;
    total.value = page.total;
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось загрузить миры';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

function open(world: LoreWorld): void {
  void router.push({ name: 'LoreWorld', params: { worldCode: world.code } });
}

function created(world: LoreWorld): void {
  void router.push({ name: 'LoreWorldSettings', params: { worldCode: world.code } });
}

function formatDate(unix: number): string {
  return new Date(unix * 1000).toLocaleDateString();
}
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions" defer>
      <v-btn v-if="canCreate" color="primary" size="small" prepend-icon="mdi-plus" @click="createOpen = true">
        Новый мир
      </v-btn>
    </Teleport>

    <v-card>
      <v-card-text>
        <div class="d-flex align-center ga-3 mb-4 flex-wrap">
          <v-text-field
            v-model="q"
            label="Поиск мира"
            prepend-inner-icon="mdi-magnify"
            density="compact"
            hide-details
            class="flex-grow-1"
            @keyup.enter="load"
          />
          <v-select
            v-model="state"
            :items="[
              { value: 'active', title: 'Активные' },
              { value: 'archived', title: 'Архив' },
              { value: 'all', title: 'Все' },
            ]"
            item-title="title"
            item-value="value"
            density="compact"
            hide-details
            style="max-width: 180px"
            @update:model-value="load"
          />
          <v-btn variant="tonal" prepend-icon="mdi-refresh" @click="load">Обновить</v-btn>
        </div>

        <v-alert v-if="error" type="error" variant="tonal" class="mb-4">
          {{ error }}
          <v-btn variant="text" size="small" @click="load">Повторить</v-btn>
        </v-alert>

        <v-progress-linear v-if="loading" indeterminate class="mb-4" />

        <v-table v-if="worlds.length > 0">
          <thead>
            <tr>
              <th>Мир</th>
              <th>Описание</th>
              <th>Видимость</th>
              <th>Обновлён</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="world in worlds" :key="world.id">
              <td>
                <v-btn variant="text" class="px-0" @click="open(world)">{{ world.name }}</v-btn>
                <v-chip v-if="world.isArchived" size="x-small" class="ml-2">архив</v-chip>
              </td>
              <td class="text-medium-emphasis">{{ world.description }}</td>
              <td>
                <v-chip size="small" :color="world.visibility === 'public' ? 'success' : undefined" variant="tonal">
                  {{ LORE_VISIBILITY_LABELS[world.visibility] ?? world.visibility }}
                </v-chip>
              </td>
              <td class="text-medium-emphasis">{{ formatDate(world.updatedAt) }}</td>
              <td class="text-right">
                <v-btn size="small" variant="tonal" @click="open(world)">Открыть</v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>

        <v-alert v-else-if="!loading" type="info" variant="tonal">
          Миров пока нет. Создайте первый мир кодекса.
        </v-alert>

        <div v-if="total > worlds.length" class="text-caption text-medium-emphasis mt-3">
          Показано {{ worlds.length }} из {{ total }}
        </div>
      </v-card-text>
    </v-card>

    <LoreWorldCreateDialog v-model="createOpen" @created="created" />
  </v-container>
</template>
