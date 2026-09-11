<script setup lang="ts">
defineProps<{
  stages: readonly {
    key: string;
    title: string;
    subtitle: string;
    warned?: boolean;
  }[];
  activeTab: string;
  collapsed: boolean;
}>();

const emit = defineEmits<{
  'update:collapsed': [value: boolean];
  select: [value: string];
}>();

function stageLabel(stage: { title: string; subtitle: string }): string {
  return `${stage.title}: ${stage.subtitle}`;
}

function selectStage(stageKey: string): void {
  emit('select', stageKey);
}
</script>

<template>
  <aside class="editor-stage-rail" :class="{ 'editor-stage-rail--collapsed': collapsed }">
    <div class="editor-stage-rail__header">
      <span v-if="!collapsed" class="text-caption text-medium-emphasis">Этапы</span>
      <v-btn
        icon
        size="small"
        variant="text"
        :aria-label="collapsed ? 'Развернуть этапы' : 'Свернуть этапы'"
        @click="emit('update:collapsed', !collapsed)"
      >
        <v-icon :icon="collapsed ? 'mdi-chevron-right' : 'mdi-chevron-left'" />
      </v-btn>
    </div>

    <nav class="editor-stage-rail__list" aria-label="Этапы создания персонажа">
      <v-tooltip v-for="(stage, index) in stages" :key="stage.key" location="right">
        <template #activator="{ props: tooltipProps }">
          <button
            v-bind="tooltipProps"
            type="button"
            class="editor-stage-rail__stage"
            :class="{
              'editor-stage-rail__stage--active': activeTab === stage.key,
              'editor-stage-rail__stage--warned': stage.warned,
            }"
            :aria-label="stageLabel(stage)"
            :aria-current="activeTab === stage.key ? 'step' : undefined"
            @click="selectStage(stage.key)"
          >
            <span class="editor-stage-rail__index">{{ index + 1 }}</span>
            <span v-if="!collapsed" class="editor-stage-rail__copy">
              <span class="editor-stage-rail__title">{{ stage.title }}</span>
              <span class="editor-stage-rail__subtitle">{{ stage.subtitle }}</span>
            </span>
          </button>
        </template>
        <span>{{ stageLabel(stage) }}</span>
      </v-tooltip>
    </nav>
  </aside>
</template>

<style scoped>
.editor-stage-rail {
  display: flex;
  flex-direction: column;
  gap: 8px;
  height: calc(100dvh - 66px);
  max-height: calc(100dvh - 66px);
  min-width: 196px;
  padding: 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  background: rgb(var(--v-theme-surface));
  overflow-y: auto;
}

.editor-stage-rail--collapsed {
  min-width: 56px;
  align-items: center;
}

.editor-stage-rail__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 32px;
}

.editor-stage-rail--collapsed .editor-stage-rail__header {
  justify-content: center;
}

.editor-stage-rail__list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.editor-stage-rail__stage {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 44px;
  padding: 5px 8px;
  border: 1px solid transparent;
  background: transparent;
  color: inherit;
  cursor: pointer;
  text-align: left;
}

.editor-stage-rail--collapsed .editor-stage-rail__stage {
  justify-content: center;
  width: 38px;
  padding: 5px;
}

.editor-stage-rail__stage:hover {
  background: rgba(var(--v-theme-primary), 0.06);
}

.editor-stage-rail__stage--active {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background: rgba(var(--v-theme-primary), 0.12);
}

.editor-stage-rail__stage--warned {
  border-color: rgb(var(--v-theme-error));
}

.editor-stage-rail__index {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  flex: 0 0 24px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 50%;
  font-size: 12px;
  font-weight: 600;
}

.editor-stage-rail__copy {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 2px;
}

.editor-stage-rail__title,
.editor-stage-rail__subtitle {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.editor-stage-rail__title {
  font-size: 13px;
  font-weight: 500;
}

.editor-stage-rail__subtitle {
  color: rgba(var(--v-theme-on-surface), 0.72);
  font-size: 11px;
}

.editor-stage-rail__stage--warned .editor-stage-rail__subtitle {
  color: rgb(var(--v-theme-error));
}
</style>
