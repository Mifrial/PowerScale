<script setup lang="ts">
import { computed } from 'vue';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import { GAME_VISIBILITY_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameVisibility/GAME_VISIBILITY_OPTIONS';
import { GAME_STATUS_OPTIONS } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_OPTIONS';
import { GAME_STATUS_COLOR } from '@/modules/Roleplay/Game/Constant/GameStatus/GAME_STATUS_COLOR';

const props = defineProps<{
  detail: GameDetail;
  spaceName: string | null;
}>();

const emit = defineEmits<{ 'open-owner': [] }>();

const revisionHref = computed(() => `/space/${props.detail.game.spaceCode}/${props.detail.game.rulesRevision}`);

const revisionLabel = computed(
  () => `Правила: ${props.spaceName ?? props.detail.game.spaceCode} v${props.detail.game.rulesRevision}`,
);

const statusLabel = computed(
  () =>
    GAME_STATUS_OPTIONS.find((option) => option.value === props.detail.game.status)?.label ?? props.detail.game.status,
);

const visibilityLabel = computed(
  () =>
    GAME_VISIBILITY_OPTIONS.find((option) => option.value === props.detail.game.visibility)?.label ??
    props.detail.game.visibility,
);

function limitValue(value: number | null): string {
  return value === null ? '—' : String(value);
}

const limitCells = computed(() => [
  { label: 'ОС', value: limitValue(props.detail.osPointsLimit) },
  { label: 'ОЛ', value: limitValue(props.detail.olPointsLimit) },
  { label: 'ОР', value: limitValue(props.detail.orPointsLimit) },
  { label: 'Деньги', value: `${limitValue(props.detail.moneyLimit)} гм` },
]);
</script>

<template>
  <div class="d-flex flex-column ga-4">
    <v-card v-if="detail.description">
      <v-card-title class="text-subtitle-1">Описание</v-card-title>
      <v-card-text class="text-body-2">{{ detail.description }}</v-card-text>
    </v-card>

    <v-card>
      <v-card-title class="text-subtitle-1">
        <v-btn
          variant="text"
          color="primary"
          size="small"
          class="pa-0"
          prepend-icon="mdi-book-open-variant"
          :to="revisionHref"
        >
          {{ revisionLabel }}
        </v-btn>
      </v-card-title>
      <v-card-text class="pt-0">
        <div class="text-caption text-medium-emphasis mb-1">Стартовые лимиты:</div>
        <div class="limits-row">
          <div v-for="cell in limitCells" :key="cell.label" class="limit-cell">
            <span class="limit-label">{{ cell.label }}:</span>
            <span class="limit-value">{{ cell.value }}</span>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <div class="d-flex ga-4 flex-wrap">
      <v-card v-if="detail.game.tags.length > 0 || detail.forbiddenTags.length > 0" class="flex-grow-1">
        <v-card-title class="text-subtitle-1">Теги</v-card-title>
        <v-card-text class="d-flex flex-column ga-2">
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Жанр и сеттинг:</span>
            <v-chip v-for="tag in detail.game.tags" :key="tag" size="x-small" variant="tonal">#{{ tag }}</v-chip>
            <span v-if="detail.game.tags.length === 0" class="text-body-2 text-medium-emphasis">—</span>
          </div>
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Запретные теги:</span>
            <v-chip v-for="tag in detail.forbiddenTags" :key="tag" size="x-small" variant="tonal" color="error">
              #{{ tag }}
            </v-chip>
            <span v-if="detail.forbiddenTags.length === 0" class="text-body-2 text-medium-emphasis">—</span>
          </div>
        </v-card-text>
      </v-card>

      <v-card class="flex-grow-1">
        <v-card-title class="text-subtitle-1">Прочее</v-card-title>
        <v-card-text class="d-flex flex-column ga-1">
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Статус:</span>
            <v-chip :color="GAME_STATUS_COLOR[detail.game.status]" variant="tonal" size="x-small">
              {{ statusLabel }}
            </v-chip>
          </div>
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Владелец:</span>
            <a class="owner-link" @click="emit('open-owner')">{{ detail.game.ownerName }}</a>
          </div>
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Игроков:</span>
            <span class="text-body-2">{{ detail.game.memberCount }}</span>
          </div>
          <div class="d-flex align-center ga-1 flex-wrap">
            <span class="text-caption text-medium-emphasis">Видят игру:</span>
            <span class="text-body-2">{{ visibilityLabel }}</span>
          </div>
        </v-card-text>
      </v-card>
    </div>
  </div>
</template>

<style scoped>
.owner-link {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  font-weight: 500;
}
.owner-link:hover {
  text-decoration: underline;
}

.limits-row {
  display: flex;
  width: fit-content;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  overflow: hidden;
}
.limit-cell {
  padding: 6px 12px;
  white-space: nowrap;
}
.limit-cell + .limit-cell {
  border-left: 1px solid rgba(var(--v-theme-on-surface), 0.12);
}
.limit-label {
  color: rgb(var(--v-theme-on-surface-variant));
  font-size: 0.75rem;
  margin-right: 6px;
}
.limit-value {
  font-size: 0.875rem;
}
</style>
