<script setup lang="ts">
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import ZoneCostTableEditor from '@/modules/Roleplay/Rule/Component/Editors/Zone/ZoneCostTableEditor.vue';

const props = defineProps<{
  modelValue: Extract<AbilityCost, { kind: 'parameter_table' }>;
  parameterItems: { title: string; value: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Extract<AbilityCost, { kind: 'parameter_table' }>];
}>();

function patch(partial: Partial<Extract<AbilityCost, { kind: 'parameter_table' }>>): void {
  emit('update:modelValue', { ...props.modelValue, ...partial });
}
</script>

<template>
  <div class="mt-1">
    <v-select
      :model-value="modelValue.parameter_code"
      :items="parameterItems"
      label="Параметр"
      density="compact"
      hide-details
      class="mb-2"
      style="max-width: 280px"
      @update:model-value="patch({ parameter_code: String($event ?? '') })"
    />
    <ZoneCostTableEditor :model-value="modelValue.costs" @update:model-value="patch({ costs: $event })" />
  </div>
</template>
