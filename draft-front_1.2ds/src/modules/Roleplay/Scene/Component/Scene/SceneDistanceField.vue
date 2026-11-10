<script setup lang="ts">
import { ref, watch } from 'vue';
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
  { hint: 'Формат: 40 или {1|-1}', min: 0, max: Number.MAX_SAFE_INTEGER },
);

const emit = defineEmits<{
  'update:modelValue': [value: number];
}>();

const text = ref('');
const error = ref<string | null>(null);

watch(
  () => [props.modelValue, props.resolution] as const,
  () => {
    text.value = sceneUnits.formatUnits(props.modelValue, props.resolution);
  },
  { immediate: true },
);

/** Применяет введённое значение; неверный формат возвращает текст к модели. */
function commit(): void {
  const parsed = sceneUnits.parse(text.value);
  if (parsed === null) {
    error.value = 'Нужно целое число ипари или {base|size}';
    reset();

    return;
  }

  const units = sceneUnits.fromIpari(parsed, props.resolution);
  const clamped = Math.min(props.max, Math.max(props.min, units));
  error.value = null;
  if (clamped !== props.modelValue) {
    emit('update:modelValue', clamped);
  }

  text.value = sceneUnits.formatUnits(clamped, props.resolution);
}

/** Возвращает текст к текущей модели. */
function reset(): void {
  text.value = sceneUnits.formatUnits(props.modelValue, props.resolution);
}
</script>

<template>
  <v-text-field
    :model-value="text"
    :label="props.label"
    :hint="props.hint"
    :error-messages="error === null ? [] : [error]"
    persistent-hint
    density="compact"
    variant="outlined"
    hide-details="auto"
    @update:model-value="(value) => (text = value ?? '')"
    @blur="commit"
    @keyup.enter="commit"
  />
</template>
