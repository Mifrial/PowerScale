<script setup lang="ts">
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/FilterValue';

defineProps<{
  field: FilterField;
  modelValue?: FilterOptionValue | null;
}>();

defineEmits<{
  'update:modelValue': [value: FilterOptionValue | null | undefined];
}>();
</script>

<template>
  <v-select
    :model-value="modelValue"
    :label="field.label"
    :items="field.options ?? []"
    item-title="label"
    item-value="value"
    density="compact"
    hide-details
    variant="outlined"
    clearable
    class="flex-grow-1"
    @click.stop
    @update:model-value="$emit('update:modelValue', $event)"
  />
</template>
