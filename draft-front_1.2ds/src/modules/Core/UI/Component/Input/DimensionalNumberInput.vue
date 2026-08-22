<script setup lang="ts">
import DimensionalNumber from '@/modules/Core/UI/Component/Input/DimensionalNumber.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

const DEFAULT_VALUE: DimensionalNumberValue = { base: 3, size: 0 };

const props = withDefaults(
  defineProps<{
    modelValue: DimensionalNumberValue | null;
    label?: string;
    min?: number;
    max?: number;
  }>(),
  {
    label: undefined,
    min: 0,
    max: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: DimensionalNumberValue | null];
}>();

function updateBase(val: number) {
  const current = props.modelValue ?? DEFAULT_VALUE;
  emit('update:modelValue', {
    base: val,
    size: current.size,
  });
}

function updateSize(val: number) {
  const current = props.modelValue ?? DEFAULT_VALUE;
  emit('update:modelValue', {
    base: current.base,
    size: val,
  });
}
</script>

<template>
  <div
    style="
      flex: 1 1 auto;
      min-width: 0;
      position: relative;
      border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
      border-radius: 4px;
      padding: 8px 8px 4px;
    "
  >
    <span
      v-if="label"
      class="text-medium-emphasis text-caption"
      style="position: absolute; top: -8px; left: 8px; background: rgb(var(--v-theme-surface)); padding: 0 4px"
    >
      {{ label }}
    </span>
    <div class="d-flex align-center ga-2">
      <DimensionalNumber v-if="modelValue && !isNaN(modelValue.base) && !isNaN(modelValue.size)" :value="modelValue" />
      <v-chip v-else size="x-small" variant="outlined">-</v-chip>
      <ClampedNumberField
        :model-value="modelValue?.base ?? 3"
        @update:model-value="updateBase"
        label="База"
        :min="min"
        :max="max"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <ClampedNumberField
        :model-value="modelValue?.size ?? 0"
        @update:model-value="updateSize"
        label="Размер"
        reverse
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
    </div>
  </div>
</template>
