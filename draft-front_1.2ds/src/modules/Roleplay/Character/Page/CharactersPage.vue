<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { canViewCharacter } from '@/modules/Roleplay/Character/Utils/access';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { accessService } from '@/modules/Core/User/init';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import { filterFields } from '@/modules/Roleplay/Character/Constant/charactersGridManifest';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';

const router = useRouter();
const store = useCharacterStore();
const userStore = useUserStore();
const { loading } = storeToRefs(store);
const { signal } = useAbortable();

const canCreate = computed(() => accessService.hasAnyPermission(userStore.currentUser, ['character.create']));

// «Полностью невидим»: персонажи, к которым у текущего пользователя нет доступа по зонам, не в списке.
const visibleCharacters = computed(() =>
  store.characters.filter((character) => canViewCharacter(userStore.currentUser, character)),
);

const rows = computed(() =>
  visibleCharacters.value.map((character) => ({ ...character, inGame: character.gameId !== null })),
);

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => rows.value,
  fields: filterFields,
  searchFields: ['name', 'raceLabel', 'ownerName', 'gameName'],
});

function statusLabel(status: CharacterStatus): string {
  return CHARACTER_STATUS_OPTIONS.find((option) => option.value === status)?.label ?? status;
}

function statusColor(status: CharacterStatus): string {
  return CHARACTER_STATUS_COLOR[status];
}

function pointsSummary(character: Character): string {
  const parts: string[] = [`ОР ${character.currentPoints.or ?? '—'}`];
  if (character.currentPoints.os !== 0) parts.push(`ОС ${character.currentPoints.os}`);
  if (character.currentPoints.ol !== 0) parts.push(`ОЛ ${character.currentPoints.ol}`);

  return parts.join(' · ');
}

onMounted(() => store.fetchCharacters(signal.value));
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Персонажи</h1>
      <v-spacer />
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="router.push('/characters/new')">
        Новый персонаж
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="characters"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="store.error" type="error" class="mt-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchCharacters(signal)">Повторить</v-btn>
      </template>
    </v-alert>

    <v-row class="mt-4">
      <v-col v-for="character in filteredRows" :key="character.id" cols="12" sm="6" md="4">
        <v-card :to="`/characters/${character.id}`" class="character-card">
          <v-card-title class="d-flex align-center character-card-title">
            <span class="character-card-name">{{ character.name }}</span>
            <v-spacer />
            <v-chip :color="statusColor(character.status)" variant="tonal" size="x-small" class="character-card-chip">
              {{ statusLabel(character.status) }}
            </v-chip>
          </v-card-title>
          <v-card-subtitle class="character-card-subtitle">
            {{ character.raceLabel ?? 'Раса не выбрана' }}
            <v-chip
              v-if="character.inGame"
              color="primary"
              variant="outlined"
              size="x-small"
              density="comfortable"
              class="character-card-game"
            >
              {{ character.gameName }}
            </v-chip>
          </v-card-subtitle>
          <v-card-text>
            <div class="text-body-2 character-card-description">
              {{ character.shortDescription ?? '—' }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Владелец: {{ character.ownerName }} · {{ pointsSummary(character) }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="filteredRows.length === 0 && !loading" class="text-center text-medium-emphasis pa-8">
      Персонажи не найдены
    </div>
  </v-container>
</template>

<style scoped>
.character-card {
  height: 100%;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
}
.character-card-title {
  min-width: 0;
}
.character-card-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.character-card-chip {
  flex-shrink: 0;
  margin-left: 8px;
}
.character-card-subtitle {
  display: flex;
  align-items: center;
  gap: 8px;
}
.character-card-game {
  flex-shrink: 0;
}
.character-card-description {
  margin-bottom: 8px;
}
</style>
