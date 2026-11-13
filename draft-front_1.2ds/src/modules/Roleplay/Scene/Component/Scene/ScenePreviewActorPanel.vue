<script setup lang="ts">
import type { ScenePreviewActor } from '@/modules/Roleplay/Scene/Dto/ScenePreviewActor';
import SceneDistanceField from '@/modules/Roleplay/Scene/Component/Scene/SceneDistanceField.vue';

defineProps<{
  actors: ScenePreviewActor[];
  selected: ScenePreviewActor | null;
  resolution: number;
}>();

defineEmits<{
  select: [id: number];
  sight: [id: number, radius: number];
}>();
</script>

<template>
  <div v-if="actors.length > 0" class="scene-actor-panel" @pointerdown.stop @wheel.stop>
    <div class="scene-actor-panel__tabs">
      <v-chip
        v-for="actor in actors"
        :key="actor.id"
        size="x-small"
        :color="selected?.id === actor.id ? 'primary' : undefined"
        :variant="actor.ghost ? 'outlined' : 'tonal'"
        @click="$emit('select', actor.id)"
      >
        {{ actor.label }}
      </v-chip>
    </div>

    <template v-if="selected !== null">
      <SceneDistanceField
        :model-value="selected.sightRadius"
        :resolution="resolution"
        label="Обзор, ипари"
        hint="Клик по карте — маршрут; клик по актору — переключить наблюдателя."
        :min="resolution"
        :max="100000"
        @update:model-value="(value) => $emit('sight', selected?.id ?? 0, value)"
      />
    </template>
  </div>
</template>

<style scoped>
.scene-actor-panel {
  position: absolute;
  right: 12px;
  bottom: 12px;
  z-index: 3;
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: min(260px, calc(100% - 24px));
  padding: 8px 10px;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 10px;
  background: rgba(15, 23, 42, 0.86);
  backdrop-filter: blur(4px);
}

.scene-actor-panel__tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
