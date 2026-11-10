<script setup lang="ts">
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';

const props = defineProps<{
  tool: SceneTool;
  isDirty: boolean;
  saving: boolean;
  canSave: boolean;
  hasSelection: boolean;
}>();

const emit = defineEmits<{
  'update:tool': [tool: SceneTool];
  save: [];
  remove: [];
}>();
</script>

<template>
  <div class="scene-toolbar">
    <v-btn-group variant="tonal" density="comfortable">
      <v-btn
        v-for="item in SCENE_TOOLS"
        :key="item.id"
        :icon="item.icon"
        :title="item.label"
        :color="props.tool === item.id ? 'primary' : undefined"
        :variant="props.tool === item.id ? 'flat' : 'tonal'"
        @click="emit('update:tool', item.id)"
      />
    </v-btn-group>

    <v-btn
      variant="tonal"
      color="error"
      prepend-icon="mdi-delete"
      :disabled="!props.hasSelection"
      @click="emit('remove')"
    >
      Удалить
    </v-btn>

    <v-spacer />

    <v-chip v-if="props.isDirty" size="small" color="warning" variant="tonal">Есть изменения</v-chip>
    <v-btn
      color="primary"
      prepend-icon="mdi-content-save"
      :loading="props.saving"
      :disabled="!props.canSave || props.saving"
      @click="emit('save')"
    >
      Сохранить
    </v-btn>
  </div>
</template>

<style scoped>
.scene-toolbar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
}
</style>
