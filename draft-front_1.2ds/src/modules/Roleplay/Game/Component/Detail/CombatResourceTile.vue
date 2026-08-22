<script setup lang="ts">
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import CombatResourcePopup from '@/modules/Roleplay/Game/Component/Detail/CombatResourcePopup.vue';

withDefaults(
  defineProps<{
    resource: ResourceOverview;
    rules: Rule[];
    /** Кнопки +/− доступны (CD-6: ГМ или владелец своего approved-персонажа). */
    canEdit?: boolean;
  }>(),
  {
    canEdit: false,
  },
);

const emit = defineEmits<{
  /** Изменить текущее значение ресурса на delta (в базовых пунктах). */
  change: [resource: ResourceOverview, delta: number];
}>();
</script>

<template>
  <v-menu location="right top" :close-on-content-click="false" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-resource" role="button" tabindex="0">
        <span class="combat-card-resource__label text-truncate">{{ resource.name }}</span>
        <span class="combat-card-resource__value">{{ resource.currentLabel }} / {{ resource.maxLabel }}</span>
        <template v-if="canEdit">
          <button
            v-if="resource.current.base > 0"
            type="button"
            class="combat-card-resource__step"
            :title="`Уменьшить «${resource.name}»`"
            @click.stop="emit('change', resource, -1)"
          >
            <v-icon size="x-small">mdi-minus</v-icon>
          </button>
          <button
            v-if="resource.current.base < resource.max.base"
            type="button"
            class="combat-card-resource__step"
            :title="`Увеличить «${resource.name}»`"
            @click.stop="emit('change', resource, 1)"
          >
            <v-icon size="x-small">mdi-plus</v-icon>
          </button>
        </template>
      </div>
    </template>
    <CombatResourcePopup :resource="resource" :rules="rules" />
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
.combat-card-resource__step {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.combat-card-resource__step:hover {
  color: rgb(var(--v-theme-primary));
}
</style>
