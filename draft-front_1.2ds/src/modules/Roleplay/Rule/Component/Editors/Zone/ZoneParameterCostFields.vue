<script setup lang="ts">
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  modelValue: Extract<AbilityCost, { kind: 'parameter' }>;
  parameterItems: { title: string; value: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Extract<AbilityCost, { kind: 'parameter' }>];
}>();

function patch(partial: Partial<Extract<AbilityCost, { kind: 'parameter' }>>): void {
  emit('update:modelValue', { ...props.modelValue, ...partial });
}
</script>

<template>
  <div class="d-flex ga-2 flex-wrap mt-1">
    <v-select
      :model-value="modelValue.parameter_code"
      :items="parameterItems"
      label="Параметр"
      density="compact"
      hide-details
      style="min-width: 180px"
      @update:model-value="patch({ parameter_code: String($event ?? '') })"
    />
    <ClampedNumberField
      :model-value="modelValue.per_unit"
      label="Ставка"
      density="compact"
      hide-details
      style="min-width: 120px"
      @update:model-value="patch({ per_unit: $event ?? 0 })"
    />
  </div>
</template>
