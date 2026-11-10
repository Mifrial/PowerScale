<script setup lang="ts">
import type { SceneTool } from '@/modules/Roleplay/Scene/Enum/SceneTool';
import type { SceneToolDefinition } from '@/modules/Roleplay/Scene/Dto/SceneToolDefinition';
import { SCENE_TOOLS } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_TOOLS';

const props = defineProps<{
  tool: SceneTool;
  hasSelection: boolean;
  canUndo: boolean;
  canRedo: boolean;
}>();

const emit = defineEmits<{
  'update:tool': [tool: SceneTool];
  remove: [];
  undo: [];
  redo: [];
}>();

/** Инструменты, сгруппированные по общей кнопке с меню вариантов. */
const entries = SCENE_TOOLS.reduce<{ key: string; label: string; icon: string; tools: SceneToolDefinition[] }[]>(
  (list, item) => {
    if (item.group === undefined) {
      list.push({ key: item.id, label: item.label, icon: item.icon, tools: [item] });

      return list;
    }

    const group = list.find((entry) => entry.key === item.group);
    if (group === undefined) {
      list.push({
        key: item.group,
        label: item.group === 'terrain' ? 'Террейн' : item.label,
        icon: item.icon,
        tools: [item],
      });

      return list;
    }

    group.tools.push(item);

    return list;
  },
  [],
);

/** Активный вариант группы; если выбрана другая группа — первый. */
function groupTool(entry: { tools: SceneToolDefinition[] }): SceneToolDefinition {
  return entry.tools.find((item) => item.id === props.tool) ?? entry.tools[0];
}

function isGroupActive(entry: { tools: SceneToolDefinition[] }): boolean {
  return entry.tools.some((item) => item.id === props.tool);
}
</script>

<template>
  <div class="scene-toolbar">
    <div class="scene-toolbar__tools">
      <template v-for="entry in entries" :key="entry.key">
        <v-btn
          v-if="entry.tools.length === 1"
          :prepend-icon="entry.icon"
          size="small"
          :color="props.tool === entry.key ? 'primary' : undefined"
          :variant="props.tool === entry.key ? 'flat' : 'tonal'"
          @click="emit('update:tool', entry.tools[0].id)"
        >
          {{ entry.label }}
        </v-btn>

        <v-btn-group v-else variant="tonal" divided rounded="lg" size="small" class="scene-toolbar__split">
          <v-btn
            :prepend-icon="groupTool(entry).icon"
            :color="isGroupActive(entry) ? 'primary' : undefined"
            :variant="isGroupActive(entry) ? 'flat' : undefined"
            :title="`Террейн: ${groupTool(entry).variant}`"
            @click="emit('update:tool', groupTool(entry).id)"
          >
            {{ entry.label }}
          </v-btn>
          <v-menu location="bottom start">
            <template #activator="{ props: menuProps }">
              <v-btn
                v-bind="menuProps"
                icon
                :color="isGroupActive(entry) ? 'primary' : undefined"
                :variant="isGroupActive(entry) ? 'flat' : undefined"
                class="scene-toolbar__split-toggle"
                aria-label="Варианты террейна"
                title="Варианты террейна"
              >
                <v-icon size="18">mdi-chevron-down</v-icon>
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item
                v-for="item in entry.tools"
                :key="item.id"
                :prepend-icon="item.icon"
                :title="item.variant ?? item.label"
                :active="props.tool === item.id"
                @click="emit('update:tool', item.id)"
              />
            </v-list>
          </v-menu>
        </v-btn-group>
      </template>
    </div>

    <v-divider vertical class="mx-1" />

    <v-btn
      icon="mdi-undo"
      variant="tonal"
      size="small"
      title="Отменить"
      :disabled="!props.canUndo"
      @click="emit('undo')"
    />
    <v-btn
      icon="mdi-redo"
      variant="tonal"
      size="small"
      title="Вернуть"
      :disabled="!props.canRedo"
      @click="emit('redo')"
    />

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

.scene-toolbar__split {
  gap: 0;
  align-self: center;
}

.scene-toolbar__split-toggle {
  padding-inline: 4px;
  min-width: 0;
}
</style>
