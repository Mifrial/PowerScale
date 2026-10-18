<script setup lang="ts">
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import AbilityParameterRow from '@/modules/Roleplay/Rule/Component/Editors/AbilityParameterRow.vue';
import { abilityParameterSpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilityParameterSpecService';

const props = defineProps<{
  modelValue: AbilityParameter[];
  abilities: AbilityRef[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: AbilityParameter[]];
}>();

function replace(next: AbilityParameter[]): void {
  emit('update:modelValue', next);
}

function patchAt(index: number, parameter: AbilityParameter): void {
  replace(props.modelValue.map((row, itemIndex) => (itemIndex === index ? parameter : row)));
}

function addParameter(): void {
  replace([...props.modelValue, abilityParameterSpecService.createEmpty()]);
}

function removeParameter(index: number): void {
  replace(props.modelValue.filter((_, itemIndex) => itemIndex !== index));
}
</script>

<template>
  <div>
    <AbilityParameterRow
      v-for="(parameter, index) in modelValue"
      :key="index"
      :model-value="parameter"
      :abilities="abilities"
      @update:model-value="patchAt(index, $event)"
      @remove="removeParameter(index)"
    />
    <v-btn variant="text" color="primary" size="small" @click="addParameter">
      <v-icon start>mdi-plus</v-icon>
      Добавить параметр
    </v-btn>
  </div>
</template>
