<script setup lang="ts">
import type { SceneDraft } from '@/modules/Roleplay/Scene/Dto/SceneDraft';

const props = defineProps<{
  draft: SceneDraft;
}>();

const emit = defineEmits<{
  meta: [patch: Partial<Pick<SceneDraft, 'name' | 'description' | 'resolution'>>];
}>();
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
      <v-text-field
        :model-value="props.draft.resolution"
        label="Разрешение, ед./ипари"
        type="number"
        density="compact"
        variant="outlined"
        hide-details
        @update:model-value="(value) => emit('meta', { resolution: Math.max(1, Number(value) || 1) })"
      />
    </v-card-text>
  </v-card>
</template>

<style scoped>
.scene-location-settings {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
</style>
