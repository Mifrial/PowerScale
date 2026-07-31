<template>
  <v-switch
    :model-value="modelValue"
    :label="field.label"
    density="compact"
    hide-details
    color="primary"
    inset
    class="flex-grow-1"
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
