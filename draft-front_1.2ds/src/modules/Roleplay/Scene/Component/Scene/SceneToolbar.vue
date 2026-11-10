<script setup lang="ts">
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';

const props = defineProps<{
  tool: SceneTool;
  hasSelection: boolean;
}>();

const emit = defineEmits<{
  'update:tool': [tool: SceneTool];
  remove: [];
}>();
</script>

<template>
  <div class="scene-toolbar">
    <div class="scene-toolbar__tools">
      <v-btn
        v-for="item in SCENE_TOOLS"
        :key="item.id"
        :prepend-icon="item.icon"
        size="small"
        :color="props.tool === item.id ? 'primary' : undefined"
        :variant="props.tool === item.id ? 'flat' : 'tonal'"
        @click="emit('update:tool', item.id)"
      >
        {{ item.label }}
      </v-btn>
    </div>

    <v-divider vertical class="mx-1" />

    <v-btn
      variant="tonal"
      color="error"
      prepend-icon="mdi-delete"
      size="small"
      :disabled="!props.hasSelection"
      @click="emit('remove')"
    >
      Удалить
    </v-btn>
  </div>
</template>

<style scoped>
.scene-toolbar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  margin-bottom: 4px;
}

.scene-toolbar__tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
