<script setup lang="ts">
import type { SceneSaveBoard } from '@/modules/Roleplay/Scene/Dto/SceneSaveBoard';

const props = defineProps<{
  boards: SceneSaveBoard[];
  modelValue: number | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [boardId: number];
  add: [];
}>();
</script>

<template>
  <div class="scene-board-tabs">
    <v-tabs
      :model-value="props.modelValue"
      density="compact"
      show-arrows
      @update:model-value="emit('update:modelValue', Number($event))"
    >
      <v-tab v-for="board in props.boards" :key="board.id" :value="board.id">
        {{ board.name }}
      </v-tab>
    </v-tabs>
    <v-btn icon="mdi-plus" variant="text" size="small" title="Добавить поле" @click="emit('add')" />
  </div>
</template>

<style scoped>
.scene-board-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}

.scene-board-tabs :deep(.v-tabs) {
  min-width: 0;
}
</style>
