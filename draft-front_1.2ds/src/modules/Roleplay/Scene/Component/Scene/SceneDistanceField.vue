<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import DimensionalNumberField from '@/modules/Core/UI/Component/Input/DimensionalNumberField.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { sceneUnits } from '@/modules/Roleplay/Scene/Service/Instance/sceneUnits';

const props = withDefaults(
  defineProps<{
    modelValue: number;
    resolution: number;
    label: string;
    hint?: string;
    min?: number;
    max?: number;
  }>(),
  { hint: undefined, min: 0, max: Number.MAX_SAFE_INTEGER },
);

const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

/**
 * Показываемое представление. Хранится отдельно, чтобы не «схлопывать»
 * введённые базу и размер в каноническую форму после каждого нажатия.
 */
const display = ref<DimensionalNumberValue>(sceneUnits.toIpari(props.modelValue, props.resolution));

const exponent = computed(() => Math.max(0, Math.round(Math.log2(props.resolution))));
const sizeMin = computed(() => -exponent.value);
const sizeMax = 20;

/** Предел базы при текущем размере: значение не должно выходить за max. */
const baseMax = computed(() => {
  const power = display.value.size + exponent.value;

  return Math.max(1, Math.floor(props.max / Math.max(1, 2 ** power)));
});

watch(
  () => [props.modelValue, props.resolution] as const,
  () => {
    if (sceneUnits.fromIpari(display.value, props.resolution) !== props.modelValue) {
      display.value = sceneUnits.toIpari(props.modelValue, props.resolution);
    }
  },
);

/** Применяет новое значение и возвращает его в допустимый диапазон единиц. */
function apply(next: DimensionalNumberValue | null): void {
  if (next === null) {
    return;
  }

  const units = sceneUnits.fromIpari(next, props.resolution);
  const clamped = Math.min(props.max, Math.max(props.min, units));
  display.value = clamped === units ? next : sceneUnits.toIpari(clamped, props.resolution);
  if (clamped !== props.modelValue) {
    emit('update:modelValue', clamped);
  }
}
</script>

<template>
  <DimensionalNumberField
    :model-value="display"
    :label="props.label"
    :hint="props.hint"
    :min="props.min"
    :max="baseMax"
    :size-min="sizeMin"
    :size-max="sizeMax"
    @update:model-value="apply"
  />
</template>
