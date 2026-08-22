<script setup lang="ts">
import { computed } from 'vue';
import type { StatePeriodicity, PeriodStep } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import { PERIOD_STEPS } from '@/modules/Roleplay/Rule/Constant/State/PERIOD_STEPS';

const props = defineProps<{
  modelValue: StatePeriodicity | undefined;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: StatePeriodicity];
}>();

const literalValue = computed<number>({
  get: () => (props.modelValue?.kind === 'literal' ? props.modelValue.value : 1),
  set: (v) =>
    emit('update:modelValue', {
      kind: 'literal',
      value: v,
      step: props.modelValue?.kind === 'literal' ? props.modelValue.step : 'turn',
    }),
});

const literalStep = computed<PeriodStep>({
  get: () => (props.modelValue?.kind === 'literal' ? props.modelValue.step : 'turn'),
  set: (v) =>
    emit('update:modelValue', {
      kind: 'literal',
      value: props.modelValue?.kind === 'literal' ? props.modelValue.value : 1,
      step: v,
    }),
});
</script>

<template>
  <v-row dense>
    <v-col cols="5">
      <v-text-field
        v-model.number="literalValue"
        label="Периодичность"
        type="number"
        min="1"
        density="compact"
        hide-details
      />
    </v-col>
    <v-col cols="7">
      <v-select v-model="literalStep" :items="PERIOD_STEPS" label="Шаг" density="compact" hide-details />
    </v-col>
  </v-row>
</template>
