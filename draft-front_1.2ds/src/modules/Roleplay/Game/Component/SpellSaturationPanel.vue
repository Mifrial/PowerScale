<script setup lang="ts">
import { computed } from 'vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  modelValue: number;
  rating: number;
  maxSteps: number;
  ratingPerStep: number;
  powerPerStep: number;
  sourceName: string;
  busy?: boolean;
}>();

const emit = defineEmits<{ (event: 'update:modelValue', value: number): void }>();

const remaining = computed(() => props.rating - props.modelValue * props.ratingPerStep);
const power = computed(() => props.modelValue * props.powerPerStep);

function onSteps(value: number | null): void {
  emit('update:modelValue', Math.min(props.maxSteps, Math.max(0, Math.trunc(value ?? 0))));
}
</script>

<template>
  <div>
    <div class="text-body-2 mb-3">
      {{ sourceName || 'Энергонасыщение' }}: каждый шаг стоит {{ ratingPerStep }} РУ и увеличивает Мощь заклинания на
      {{ powerPerStep }}.
    </div>
    <div class="text-body-2 mb-3">Сейчас: {{ remaining }} РУ, Мощь +{{ power }}.</div>
    <ClampedNumberField
      :model-value="modelValue"
      label="Шаги энергонасыщения"
      :min="0"
      :max="maxSteps"
      density="compact"
      hide-details
      :disabled="busy"
      @update:model-value="onSteps"
    />
  </div>
</template>
