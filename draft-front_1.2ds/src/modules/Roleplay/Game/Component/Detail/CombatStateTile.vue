<script setup lang="ts">
import { ref, watch } from 'vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

export type CombatStateDetailRow = { label: string; value: string };

const props = withDefaults(
  defineProps<{
    name: string;
    iconCode?: string | null;
    leftLabel: string;
    valueLabel: string;
    details?: CombatStateDetailRow[];
    canEdit?: boolean;
    numeric?: boolean;
    current?: number;
    minValue?: number;
  }>(),
  {
    iconCode: null,
    details: () => [],
    canEdit: false,
    numeric: true,
    current: 0,
    minValue: 0,
  },
);

const emit = defineEmits<{
  apply: [next: number];
  remove: [];
}>();

const menuOpen = ref(false);
const draft = ref(0);

watch(menuOpen, (open) => {
  if (open) draft.value = Math.max(props.minValue, props.current);
});

function submitNumeric(): void {
  if (draft.value === props.current) {
    menuOpen.value = false;

    return;
  }
  emit('apply', draft.value);
  menuOpen.value = false;
}

function submitRemove(): void {
  emit('remove');
  menuOpen.value = false;
}
</script>

<template>
  <v-menu v-model="menuOpen" location="right top" :close-on-content-click="false" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-state" role="button" tabindex="0">
        <v-icon v-if="iconCode" :icon="iconCode" size="16" class="flex-shrink-0" />
        <span class="combat-card-state__label text-truncate">{{ leftLabel }}</span>
        <span class="combat-card-state__value">{{ valueLabel }}</span>
      </div>
    </template>
    <v-card class="rounded border" elevation="8" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ name }}</v-card-title>
      <v-card-text class="pt-0">
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Сила</span>
          <span class="font-weight-medium">{{ valueLabel }}</span>
        </div>
        <div
          v-for="row in details"
          :key="row.label"
          class="d-flex align-center justify-space-between py-1 text-body-2 ga-3"
        >
          <span class="text-medium-emphasis">{{ row.label }}</span>
          <span class="font-weight-medium">{{ row.value }}</span>
        </div>
        <template v-if="canEdit && numeric">
          <ClampedNumberField
            v-model="draft"
            class="mt-3"
            label="Значение"
            :min="minValue"
            density="compact"
            hide-details
          />
          <v-btn class="mt-3" color="primary" variant="tonal" size="small" block @click="submitNumeric">
            Изменить
          </v-btn>
        </template>
        <v-btn v-else-if="canEdit" class="mt-3" color="error" variant="tonal" size="small" block @click="submitRemove">
          Убрать
        </v-btn>
      </v-card-text>
    </v-card>
  </v-menu>
</template>

<style scoped>
.combat-card-state {
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
.combat-card-state:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-card-state__label {
  flex: 1;
  font-size: 13px;
  min-width: 0;
}
.combat-card-state__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
</style>
