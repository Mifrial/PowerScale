<script setup lang="ts">
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import type { ProcessStep } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessStep';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { processSpecService } from '@/modules/Roleplay/Rule/Service/Instance/processSpecService';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  step: ProcessStep;
  stepIndex: number;
  resources: ResourceRef[];
}>();

const emit = defineEmits<{
  'update:step': [value: ProcessStep];
}>();

const { inner } = useVModelSync<ProcessStep>({
  modelValue: () => props.step,
  onCommit: (value) => emit('update:step', value),
  clone: true,
});

function stepSpec(step: ProcessStep): ProcessSpec {
  return { steps: [step], start_step_code: undefined, transition: { mode: 'free' }, failure: null };
}

function patchStep(key: string, value: unknown) {
  inner.value = processSpecService.patchStep(stepSpec(inner.value), 0, key, value).steps[0];
}

function addStepCost() {
  inner.value = processSpecService.addStepCost(stepSpec(inner.value), 0).steps[0];
}

function patchStepCost(costIndex: number, key: string, value: unknown) {
  inner.value = processSpecService.patchStepCost(stepSpec(inner.value), 0, costIndex, key, value).steps[0];
}

function removeStepCost(costIndex: number) {
  inner.value = processSpecService.removeStepCost(stepSpec(inner.value), 0, costIndex).steps[0];
}

function isDimensionalCost(costIndex: number): boolean {
  const cost = inner.value.costs[costIndex];
  if (!cost) return false;

  return processSpecService.isDimensionalCost(cost, props.resources);
}

function isMandatoryCost(costIndex: number): boolean {
  return processSpecService.isMandatoryCost(inner.value.costs, costIndex);
}
</script>

<template>
  <div>
    <v-text-field
      :model-value="inner.name"
      @update:model-value="(v) => patchStep('name', v)"
      label="Название шага"
      density="compact"
      hide-details
      class="mb-2"
    />
    <v-text-field
      :model-value="inner.code"
      @update:model-value="(v) => patchStep('code', v)"
      label="Код шага"
      density="compact"
      hide-details
      class="mb-2"
    />
    <v-textarea
      :model-value="inner.description"
      @update:model-value="(v) => patchStep('description', v)"
      label="Описание"
      density="compact"
      hide-details
      auto-grow
      rows="1"
      class="mb-2"
    />
    <div class="text-subtitle-2 mb-1">Ресурсы (стоимость шага)</div>
    <div v-for="(cost, costIndex) in inner.costs" :key="`cost-${costIndex}`" class="d-flex gap-2 mb-1">
      <v-autocomplete
        :model-value="cost.resource_code"
        @update:model-value="(v) => patchStepCost(costIndex, 'resource_code', v)"
        :items="resources"
        item-title="name"
        item-value="code"
        label="Ресурс"
        density="compact"
        hide-details
        :clearable="!isMandatoryCost(costIndex)"
        :disabled="isMandatoryCost(costIndex)"
        class="flex-grow-1"
      />
      <DimensionalNumberInput
        v-if="isDimensionalCost(costIndex)"
        :model-value="(cost.amount as DimensionalNumberValue | undefined) ?? { base: 0, size: 0 }"
        @update:model-value="(v) => patchStepCost(costIndex, 'amount', v)"
        label="Стоимость"
        style="flex: 1 1 auto"
      />
      <ClampedNumberField
        v-else
        :model-value="typeof cost.amount === 'number' ? cost.amount : 0"
        @update:model-value="(v) => patchStepCost(costIndex, 'amount', v)"
        label="Стоимость"
        :min="1"
        density="compact"
        hide-details
        style="min-width: 110px"
      />
      <v-btn
        icon
        size="small"
        color="error"
        variant="text"
        :disabled="isMandatoryCost(costIndex)"
        @click="removeStepCost(costIndex)"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn variant="text" color="primary" size="small" @click="addStepCost">
      <v-icon start>mdi-plus</v-icon>
      Добавить ресурс
    </v-btn>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
