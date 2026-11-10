<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';
import { SCENE_RESOLUTIONS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_RESOLUTIONS';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const props = defineProps<{
  draft: SceneDraft;
}>();

const emit = defineEmits<{
  meta: [patch: Partial<Pick<SceneDraft, 'name' | 'description'>>];
  resolution: [value: number];
}>();

const pendingResolution = ref<number | null>(null);
const confirmOpen = ref(false);
const resolutionHint = computed(
  () => `1 ед. = ${sceneUnits.formatUnits(1, props.draft.resolution)} ипари. Изменение пересчитает координаты.`,
);

watch(
  () => props.draft.resolution,
  () => {
    pendingResolution.value = null;
  },
);

/** Открывает подтверждение смены точности. */
function selectResolution(value: number | null): void {
  if (value === null || value === props.draft.resolution) {
    return;
  }

  pendingResolution.value = value;
  confirmOpen.value = true;
}

/** Подтверждает пересчёт координат. */
function confirmResolution(): void {
  const value = pendingResolution.value;
  confirmOpen.value = false;
  pendingResolution.value = null;
  if (value !== null) {
    emit('resolution', value);
  }
}

/** Отменяет смену точности. */
function cancelResolution(): void {
  confirmOpen.value = false;
  pendingResolution.value = null;
}
</script>

<template>
  <v-card variant="outlined">
    <v-card-title class="text-subtitle-2">Локация</v-card-title>
    <v-card-text class="scene-location-settings">
      <v-text-field
        :model-value="props.draft.name"
        label="Название"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => emit('meta', { name: value ?? '' })"
      />
      <v-textarea
        :model-value="props.draft.description"
        label="Описание"
        rows="2"
        auto-grow
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => emit('meta', { description: value ?? '' })"
      />
      <v-select
        :model-value="props.draft.resolution"
        :items="SCENE_RESOLUTIONS"
        label="Точность, ед./ипари"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="selectResolution"
      >
        <template #append-inner>
          <v-tooltip location="top" max-width="280">
            <template #activator="{ props: tooltipProps }">
              <v-icon v-bind="tooltipProps" icon="mdi-information-outline" size="16" @mousedown.stop @click.stop />
            </template>
            <span class="text-caption">{{ resolutionHint }}</span>
          </v-tooltip>
        </template>
      </v-select>
    </v-card-text>

    <v-dialog v-model="confirmOpen" max-width="440">
      <v-card>
        <v-card-title class="text-subtitle-1">Изменить точность локации?</v-card-title>
        <v-card-text class="text-body-2">
          Все координаты, размеры и высоты будут пересчитаны с округлением. Карта сохранит размер в ипари, но
          детализация изменится. Отменить это можно только перезагрузкой без сохранения.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="cancelResolution">Отмена</v-btn>
          <v-btn color="primary" variant="flat" @click="confirmResolution">Пересчитать</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<style scoped>
.scene-location-settings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
