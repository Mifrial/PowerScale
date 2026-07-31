<template>
  <div class="string-filter d-flex align-center ga-2 flex-grow-1">
    <v-select
      :model-value="mode"
      :items="modeOptions"
      variant="outlined"
      density="compact"
      hide-details
      class="string-mode"
      @click.stop
      @update:model-value="onModeChange"
    />
    <v-text-field
      :model-value="value"
      :label="field.label"
      clearable
      variant="outlined"
      density="compact"
      hide-details
      class="flex-grow-1"
      @click.stop
      @update:model-value="onValueChange"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import type { FilterField } from '@/modules/Core/UI/Interfaces/FilterField'

interface StringFilterValue {
  mode: 'equals' | 'contains'
  value: string
}

const props = defineProps<{
  field: FilterField
  modelValue: any
}>()

const emit = defineEmits<{
  'update:modelValue': [value: StringFilterValue | undefined]
}>()

function parse(v: any): StringFilterValue {
  if (v && typeof v === 'object' && 'mode' in v) {
    return { mode: v.mode === 'equals' ? 'equals' : 'contains', value: v.value ?? '' }
  }
  if (typeof v === 'string') {
    return { mode: 'contains', value: v }
  }
  return { mode: 'contains', value: '' }
}

const parsed = computed(() => parse(props.modelValue))
const mode = computed(() => parsed.value.mode)
const value = computed(() => parsed.value.value)

const modeOptions = [
  { title: 'Равно', value: 'equals' },
  { title: 'Содержит', value: 'contains' },
]

function onModeChange(m: string) {
  const mode = m === 'contains' ? 'contains' : 'equals'
  emitValue(mode, value.value)
}

function onValueChange(v: any) {
  emitValue(mode.value, v ?? '')
}

function emitValue(mode: 'equals' | 'contains', val: string) {
  if (!val) {
    emit('update:modelValue', undefined)
  } else {
    emit('update:modelValue', { mode, value: val })
  }
}
</script>

<style scoped>
.string-mode {
  width: 140px;
  flex: none;
}
.string-filter {
  min-width: 0;
}
</style>
