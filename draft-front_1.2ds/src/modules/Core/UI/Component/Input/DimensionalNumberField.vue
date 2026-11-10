<script setup lang="ts">
import { computed } from 'vue';
import DimensionalNumber from '@/modules/Core/UI/Component/Input/DimensionalNumber.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

const DEFAULT_VALUE: DimensionalNumberValue = { base: 0, size: 0 };

const props = withDefaults(
  defineProps<{
    modelValue: DimensionalNumberValue | null;
    label?: string;
    hint?: string;
    min?: number;
    max?: number;
    sizeMin?: number;
    sizeMax?: number;
    disabled?: boolean;
  }>(),
  {
    label: undefined,
    hint: undefined,
    min: 0,
    max: undefined,
    sizeMin: -20,
    sizeMax: undefined,
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: DimensionalNumberValue | null];
}>();

const current = computed<DimensionalNumberValue>(() => props.modelValue ?? DEFAULT_VALUE);
const hasValue = computed(
  () => props.modelValue !== null && !isNaN(props.modelValue.base) && !isNaN(props.modelValue.size),
);

function updateBase(value: number | null): void {
  emit('update:modelValue', {
    base: value ?? current.value.base,
    size: current.value.size,
  });
}

function updateSize(value: number | null): void {
  emit('update:modelValue', {
    base: current.value.base,
    size: value ?? current.value.size,
  });
}
</script>

<template>
  <div class="dimensional-number-field" :class="{ 'dimensional-number-field--disabled': props.disabled }">
    <div v-if="props.label !== undefined || props.hint !== undefined" class="dimensional-number-field__head">
      <span v-if="props.label !== undefined" class="dimensional-number-field__label">{{ props.label }}</span>
      <v-tooltip v-if="props.hint !== undefined" location="top" max-width="280">
        <template #activator="{ props: tooltipProps }">
          <v-icon
            v-bind="tooltipProps"
            icon="mdi-information-outline"
            size="15"
            class="dimensional-number-field__hint"
          />
        </template>
        <span class="text-caption">{{ props.hint }}</span>
      </v-tooltip>
    </div>

    <v-menu location="bottom start" :close-on-content-click="false" :disabled="props.disabled">
      <template #activator="{ props: menuProps }">
        <button v-bind="menuProps" type="button" class="dimensional-number-field__value" :disabled="props.disabled">
          <DimensionalNumber v-if="hasValue" :value="current" hide-zero-size />
          <v-chip v-else size="small" variant="outlined">—</v-chip>
          <v-icon icon="mdi-menu-down" size="18" class="dimensional-number-field__caret" />
        </button>
      </template>

      <v-card min-width="260">
        <v-card-text class="dimensional-number-field__popup">
          <div class="text-caption text-medium-emphasis">Значение = база × 2<sup>размер</sup></div>
          <div class="dimensional-number-field__preview">
            <DimensionalNumber v-if="hasValue" :value="current" hide-zero-size />
            <v-chip v-else size="small" variant="outlined">—</v-chip>
          </div>
          <div class="d-flex align-center ga-2">
            <ClampedNumberField
              :model-value="current.base"
              :min="props.min"
              :max="props.max"
              label="База"
              density="compact"
              hide-details
              style="flex: 1 1 auto"
              @update:model-value="updateBase"
            />
            <ClampedNumberField
              :model-value="current.size"
              :min="props.sizeMin"
              :max="props.sizeMax"
              label="Размер"
              density="compact"
              hide-details
              reverse
              style="flex: 1 1 auto"
              @update:model-value="updateSize"
            />
          </div>
        </v-card-text>
      </v-card>
    </v-menu>
  </div>
</template>

<style scoped>
.dimensional-number-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  min-width: 0;
}

.dimensional-number-field--disabled {
  opacity: 0.6;
}

.dimensional-number-field__head {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 16px;
}

.dimensional-number-field__label {
  font-size: 0.75rem;
  color: rgb(var(--v-theme-on-surface-variant));
}

.dimensional-number-field__hint {
  color: rgb(var(--v-theme-on-surface-variant));
  cursor: help;
}

.dimensional-number-field__value {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  width: 100%;
  min-height: 40px;
  padding: 4px 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
  background: transparent;
  color: inherit;
  cursor: pointer;
}

.dimensional-number-field__value:hover {
  border-color: rgb(var(--v-theme-primary));
}

.dimensional-number-field__value:disabled {
  cursor: default;
}

.dimensional-number-field__caret {
  color: rgb(var(--v-theme-on-surface-variant));
}

.dimensional-number-field__popup {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dimensional-number-field__preview {
  display: flex;
  align-items: center;
}
</style>
