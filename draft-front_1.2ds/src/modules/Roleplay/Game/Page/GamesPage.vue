<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { accessService } from '@/modules/Core/User/init';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import { filterFields } from '@/modules/Roleplay/Game/Constant/gamesGridManifest';
import { GAME_STATUS_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_OPTIONS';
import { GAME_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_COLOR';
import { GAME_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameVisibility/GAME_VISIBILITY_OPTIONS';
import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';
import type { GameVisibility } from '@/modules/Roleplay/Game/Enum/GameVisibility';

const router = useRouter();
const store = useGameStore();
const userStore = useUserStore();
const { loading } = storeToRefs(store);
const { signal } = useAbortable();

const canCreate = computed(() => accessService.hasAnyPermission(userStore.currentUser, ['game.create']));

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => store.games,
  fields: filterFields,
  searchFields: ['name', 'ownerName'],
});

function statusLabel(status: GameStatus): string {
  return GAME_STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status;
}

function statusColor(status: GameStatus): string {
  return GAME_STATUS_COLOR[status];
}

function visibilityLabel(visibility: GameVisibility): string {
  return GAME_VISIBILITY_OPTIONS.find((option) => option.value === visibility)?.label ?? visibility;
}

onMounted(() => store.fetchGames(signal.value));
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Игры</h1>
      <v-spacer />
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="router.push('/games/new')">
        Новая игра
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="games"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="store.error" type="error" class="mt-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchGames(signal)">Повторить</v-btn>
      </template>
    </v-alert>

    <v-row class="mt-4">
      <v-col v-for="game in filteredRows" :key="game.id" cols="12" sm="6" md="4">
        <v-card :to="`/games/${game.id}`" class="game-card">
          <v-card-title class="d-flex align-center game-card-title">
            <span class="game-card-name">{{ game.name }}</span>
            <v-spacer />
            <v-chip :color="statusColor(game.status)" variant="tonal" size="x-small" class="game-card-chip">
              {{ statusLabel(game.status) }}
            </v-chip>
          </v-card-title>
          <v-card-subtitle class="game-card-subtitle">
            {{ game.ownerName }}
            <v-chip color="primary" variant="outlined" size="x-small" density="comfortable">
              {{ game.spaceCode }} v{{ game.rulesRevision }}
            </v-chip>
          </v-card-subtitle>
          <v-card-text>
            <div class="text-body-2 game-card-description">
              {{ game.shortDescription ?? '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Видимость: {{ visibilityLabel(game.visibility) }} · Участников: {{ game.memberCount }}
            </div>
            <div v-if="game.tags.length > 0" class="game-card-tags">
              <v-chip v-for="tag in game.tags" :key="tag" size="x-small" variant="text" density="comfortable">
                #{{ tag }}
              </v-chip>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="filteredRows.length === 0 && !loading" class="text-center text-medium-emphasis pa-8">
      Игры не найдены
    </div>
  </v-container>
</template>

<style scoped>
.game-card {
  height: 100%;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}
.game-card-title {
  min-width: 0;
}
.game-card-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.game-card-chip {
  flex-shrink: 0;
  margin-left: 8px;
}
.game-card-subtitle {
  display: flex;
  align-items: center;
  gap: 8px;
}
.game-card-description {
  margin-bottom: 8px;
}
.game-card-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 2px;
  margin-top: 4px;
}
</style>
