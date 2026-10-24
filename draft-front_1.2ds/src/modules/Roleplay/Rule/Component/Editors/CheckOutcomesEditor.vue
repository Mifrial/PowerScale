<script setup lang="ts">
import type { CheckOutcome } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import { CHECK_OUTCOME_KIND_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_OUTCOME_KIND_OPTIONS';
import { checkSpecService } from '@/modules/Roleplay/Rule/Service/Instance/checkSpecService';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { computed } from 'vue';

const props = defineProps<{
  outcomes?: CheckOutcome[];
  stateOptions: { title: string; value: string }[];
}>();

const emit = defineEmits<{
  'update:outcomes': [value: CheckOutcome[]];
}>();

const outcomes = computed(() => props.outcomes ?? []);

function getOutcomeKind(outcome: CheckOutcome): 'rating' | 'max' {
  return checkSpecService.getOutcomeKind(outcome);
}

function addOutcome(): void {
  emit('update:outcomes', checkSpecService.addOutcome(outcomes.value));
}

function removeOutcome(index: number): void {
  emit('update:outcomes', checkSpecService.removeOutcome(outcomes.value, index));
}

function setOutcomeKind(index: number, kind: unknown): void {
  emit('update:outcomes', checkSpecService.setOutcomeKind(outcomes.value, index, String(kind)));
}

function setOutcomeRating(index: number, value: unknown): void {
  emit('update:outcomes', checkSpecService.setOutcomeRating(outcomes.value, index, value));
}

function setOutcomeMaxRating(index: number, value: unknown): void {
  emit('update:outcomes', checkSpecService.setOutcomeMaxRating(outcomes.value, index, value));
}

function setOutcomeState(index: number, value: unknown): void {
  emit('update:outcomes', checkSpecService.setOutcomeState(outcomes.value, index, value));
}
</script>

<template>
  <div class="text-subtitle-2 mb-2">Исходы по рейтингу</div>
  <div class="text-caption mb-2">Если ни один исход не подошёл, состояние снимается: 0 и выше — чисто.</div>
  <div v-for="(outcome, index) in outcomes" :key="index" class="d-flex ga-2 align-center mb-2">
    <v-select
      :model-value="getOutcomeKind(outcome)"
      :items="CHECK_OUTCOME_KIND_OPTIONS"
      item-title="title"
      item-value="value"
      label="Порог"
      density="compact"
      hide-details
      style="max-width: 160px"
      @update:model-value="(kind) => setOutcomeKind(index, kind)"
    />
    <ClampedNumberField
      v-if="getOutcomeKind(outcome) === 'rating'"
      :model-value="outcome.rating ?? -1"
      label="РУ"
      density="compact"
      hide-details
      style="max-width: 120px"
      @update:model-value="(value) => setOutcomeRating(index, value)"
    />
    <ClampedNumberField
      v-else
      :model-value="outcome.max_rating ?? -1"
      label="Не выше РУ"
      density="compact"
      hide-details
      style="max-width: 140px"
      @update:model-value="(value) => setOutcomeMaxRating(index, value)"
    />
    <v-select
      :model-value="outcome.state_rule_code ?? null"
      :items="stateOptions"
      label="Состояние"
      placeholder="Чисто"
      density="compact"
      clearable
      hide-details
      class="flex-grow-1"
      @update:model-value="(value) => setOutcomeState(index, value)"
    />
    <v-btn icon size="small" variant="text" color="error" @click="removeOutcome(index)">
      <v-icon>mdi-close</v-icon>
    </v-btn>
  </div>
  <v-btn variant="text" color="primary" size="small" @click="addOutcome">
    <v-icon start>mdi-plus</v-icon>
    Исход
  </v-btn>
</template>
