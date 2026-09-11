<script setup lang="ts">
import type { SpellParamsView } from '@/modules/Roleplay/Character/Dto/Editor/SpellParamsView';

defineProps<{
  params: SpellParamsView;
}>();
</script>

<template>
  <div class="spell-params">
    <div class="spell-params__grid">
      <div class="param-cell"><span class="param-cell__label">Требует мощь:</span> {{ params.powerLabel }}</div>
      <div class="param-cell"><span class="param-cell__label">Требует контроль:</span> {{ params.controlLabel }}</div>
      <div v-if="params.creationLabel" class="param-cell">
        <span class="param-cell__label">Создание:</span> {{ params.creationLabel }}
      </div>
      <div class="param-cell"><span class="param-cell__label">Действие:</span> {{ params.durationLabel }}</div>
    </div>
    <div v-if="params.componentsLabel" class="spell-params__components">
      <span class="param-cell__label">Компоненты:</span> {{ params.componentsLabel }}
    </div>
  </div>
</template>

<style scoped>
.spell-params {
  margin-bottom: 8px;
}

.spell-params__grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
  font-size: 12px;
}

.spell-params__components {
  margin-top: 6px;
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  font-size: 12px;
}

.param-cell {
  padding: 4px 10px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}

.param-cell__label {
  color: rgba(var(--v-theme-on-surface), 0.72);
}
</style>
