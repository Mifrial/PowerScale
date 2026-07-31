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

<script setup lang="ts">
import type { FilterField } from '@/modules/Core/UI/Interfaces/FilterField'

defineProps<{
  field: FilterField
  modelValue: any
}>()

defineEmits<{
  'update:modelValue': [value: any]
}>()
</script>
