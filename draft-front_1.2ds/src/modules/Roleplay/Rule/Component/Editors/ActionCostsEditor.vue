<script setup lang="ts">
import type { ActionCost } from '@/modules/Roleplay/Rule/Dto/Ability/ActionCost';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';

const props = defineProps<{
  modelValue: ActionCost[];
  resources: ResourceRef[];
  isSpell: boolean;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: ActionCost[]];
}>();

const { inner } = useVModelSync<ActionCost[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: true,
});

function actionResourceIsDimensional(resource_code: string): boolean {
  return props.resources.find((r) => r.code === resource_code)?.isDimensional ?? false;
}

function updateActionCost(index: number, key: 'resource_code' | 'amount', value: unknown) {
  let cost = { ...inner.value[index], [key]: value } as ActionCost;
  if (key === 'resource_code') {
    const code = value as string;
    const isDim = actionResourceIsDimensional(code);
    if (isDim && typeof cost.amount === 'number') {
      cost = { ...cost, amount: { base: cost.amount, size: 0 } };
    } else if (!isDim && cost.amount && typeof cost.amount === 'object' && !Array.isArray(cost.amount)) {
      cost = { ...cost, amount: cost.amount.base };
    }
    if (value === 'action-points' && props.isSpell) {
      cost = { ...cost, label: 'Сотворение' };
    }
  }
  inner.value = inner.value.map((c, i) => (i === index ? cost : c));
}

function removeActionCost(index: number) {
  if (isMandatoryActionPointCost(index)) return;
  inner.value = inner.value.filter((_, i) => i !== index);
}

function addActionCost() {
  inner.value = [...inner.value, { resource_code: '', amount: 0, label: props.isSpell ? 'Сотворение' : undefined }];
}

function isMandatoryActionPointCost(index: number): boolean {
  const odIndex = inner.value.findIndex((c) => c.resource_code === 'action-points');

  return odIndex === index;
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Любое действие стоит минимум 1 ОД. {{ isSpell ? 'У заклинаний ОД называется «Сотворение».' : '' }}
    </div>
    <div v-for="(cost, index) in inner" :key="`a-${index}`" class="d-flex gap-2 mb-1">
      <v-autocomplete
        :model-value="cost.resource_code"
        @update:model-value="updateActionCost(index, 'resource_code', $event)"
        :items="resources"
        item-title="name"
        item-value="code"
        label="Ресурс"
        density="compact"
        hide-details
        :clearable="!isMandatoryActionPointCost(index)"
        :disabled="isMandatoryActionPointCost(index)"
        class="flex-grow-1"
      />
      <DimensionalNumberInput
        v-if="actionResourceIsDimensional(cost.resource_code)"
        :model-value="(cost.amount as DimensionalNumberValue | undefined) ?? { base: 0, size: 0 }"
        @update:model-value="(v) => updateActionCost(index, 'amount', v)"
        label="Стоимость"
        style="flex: 1 1 auto"
      />
      <ClampedNumberField
        v-else
        :model-value="typeof cost.amount === 'number' ? cost.amount : 0"
        @update:model-value="updateActionCost(index, 'amount', $event)"
        label="Стоимость"
        :min="1"
        density="compact"
        hide-details
        style="min-width: 120px"
      />
      <v-btn
        icon
        size="small"
        color="error"
        variant="text"
        :disabled="isMandatoryActionPointCost(index)"
        @click="removeActionCost(index)"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn variant="text" color="primary" size="small" @click="addActionCost">
      <v-icon start>mdi-plus</v-icon>
      Добавить стоимость
    </v-btn>
  </div>
</template>
