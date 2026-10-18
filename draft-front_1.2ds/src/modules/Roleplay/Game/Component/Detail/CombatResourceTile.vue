<script setup lang="ts">
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import CombatResourcePopup from '@/modules/Roleplay/Game/Component/Detail/CombatResourcePopup.vue';

withDefaults(
  defineProps<{
    resource: ResourceOverview;
    rules: Rule[];
    canEdit?: boolean;
  }>(),
  {
    canEdit: false,
  },
);

const emit = defineEmits<{
  apply: [resource: ResourceOverview, current: DimensionalNumberValue];
}>();
</script>

<template>
  <v-menu location="right top" attach="body" :close-on-content-click="false" :z-index="4000">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-resource" role="button" tabindex="0">
        <span class="combat-card-resource__label text-truncate">{{ resource.name }}</span>
        <span class="combat-card-resource__value">{{ resource.currentLabel }} / {{ resource.maxLabel }}</span>
      </div>
    </template>
    <CombatResourcePopup
      :resource="resource"
      :rules="rules"
      :can-edit="canEdit"
      @apply="(current) => emit('apply', resource, current)"
    />
  </v-menu>
</template>

<style scoped>
.combat-card-resource {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 3px 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  border-radius: 8px;
  cursor: pointer;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}
.combat-card-resource:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-card-resource__label {
  flex: 1;
  font-size: 13px;
}
.combat-card-resource__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
</style>
