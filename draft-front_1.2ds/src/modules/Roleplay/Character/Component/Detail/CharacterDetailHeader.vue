<script setup lang="ts">
import { computed } from 'vue';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import { CHARACTER_STATUS_COLOR } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_COLOR';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';

/** Шапка деталки персонажа/НПС: раса, имя, статус, ревизия, владелец и игра. */
const props = withDefaults(
  defineProps<{
    name: string;
    raceLabel?: string | null;
    status?: CharacterStatus | null;
    /** Дополнительная подпись (статус членства, «На модерации» у НПС). */
    chipLabel?: string | null;
    chipColor?: string | null;
    revisionHref?: string | null;
    revisionLabel?: string | null;
    ownerName?: string | null;
    gameName?: string | null;
  }>(),
  {
    raceLabel: null,
    status: null,
    chipLabel: null,
    chipColor: null,
    revisionHref: null,
    revisionLabel: null,
    ownerName: null,
    gameName: null,
  },
);

const statusLabel = computed(() => {
  if (!props.status) return null;

  return CHARACTER_STATUS_OPTIONS.find((option) => option.value === props.status)?.label ?? props.status;
});

const statusColor = computed(() => (props.status ? CHARACTER_STATUS_COLOR[props.status] : null));
</script>

<template>
  <div class="d-flex align-center mb-4">
    <div class="d-flex flex-column ga-1">
      <div class="d-flex align-center ga-2">
        <v-chip v-if="raceLabel" variant="tonal" size="small">{{ raceLabel }}</v-chip>
        <h1 class="text-h5">{{ name }}</h1>
      </div>
      <div class="d-flex align-center ga-2 flex-wrap">
        <v-chip v-if="statusLabel" :color="statusColor ?? undefined" variant="tonal" size="small">
          {{ statusLabel }}
        </v-chip>
        <v-chip v-if="chipLabel" :color="chipColor ?? undefined" variant="tonal" size="small">
          {{ chipLabel }}
        </v-chip>
        <v-btn
          v-if="revisionHref && revisionLabel"
          variant="text"
          color="primary"
          size="small"
          class="pa-0"
          prepend-icon="mdi-book-open-variant"
          :to="revisionHref"
        >
          {{ revisionLabel }}
        </v-btn>
        <span v-if="ownerName" class="text-caption text-medium-emphasis">владелец: {{ ownerName }}</span>
        <v-chip v-if="gameName" variant="tonal" size="small" prepend-icon="mdi-dice-multiple">
          {{ gameName }}
        </v-chip>
      </div>
    </div>
  </div>
</template>
