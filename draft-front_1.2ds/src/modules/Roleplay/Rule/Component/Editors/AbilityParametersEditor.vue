<script setup lang="ts">
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { ParameterValueKind } from '@/modules/Roleplay/Rule/Enum/Ability/ParameterValueKind';

const props = defineProps<{
  modelValue: AbilityParameter[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: AbilityParameter[]];
}>();

const kindItems: { title: string; value: ParameterValueKind }[] = [
  { title: 'Скаляр', value: 'scalar' },
  { title: 'Размерное значение', value: 'dimensional' },
];

const resolutionItems = [
  { title: 'При покупке', value: 'purchase' },
  { title: 'При активации', value: 'activation' },
];

function replace(next: AbilityParameter[]): void {
  emit('update:modelValue', next);
}

function patch(index: number, patchValue: Partial<AbilityParameter>): void {
  replace(
    props.modelValue.map((parameter, itemIndex) => (itemIndex === index ? { ...parameter, ...patchValue } : parameter)),
  );
}

function addParameter(): void {
  replace([...props.modelValue, { code: '', label: '', kind: 'scalar', resolution: 'purchase', default: 0 }]);
}

function removeParameter(index: number): void {
  replace(props.modelValue.filter((_, itemIndex) => itemIndex !== index));
}
</script>

<template>
  <div>
    <div v-for="(parameter, index) in modelValue" :key="index" class="d-flex ga-2 align-center mb-2">
      <v-text-field
        :model-value="parameter.code"
        label="Код"
        density="compact"
        hide-details
        @update:model-value="patch(index, { code: String($event) })"
      />
      <v-text-field
        :model-value="parameter.label"
        label="Подпись"
        density="compact"
        hide-details
        @update:model-value="patch(index, { label: String($event) })"
      />
      <v-select
        :model-value="parameter.kind"
        :items="kindItems"
        label="Тип значения"
        density="compact"
        hide-details
        @update:model-value="patch(index, { kind: $event as ParameterValueKind })"
      />
      <v-select
        :model-value="parameter.resolution"
        :items="resolutionItems"
        label="Когда выбирается"
        density="compact"
        hide-details
        @update:model-value="patch(index, { resolution: $event as AbilityParameter['resolution'] })"
      />
      <v-btn icon size="small" color="error" variant="text" @click="removeParameter(index)">
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn variant="text" color="primary" size="small" @click="addParameter">
      <v-icon start>mdi-plus</v-icon>
      Добавить параметр
    </v-btn>
  </div>
</template>
