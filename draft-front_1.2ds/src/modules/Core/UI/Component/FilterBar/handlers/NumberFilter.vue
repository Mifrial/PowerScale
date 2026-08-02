<template>
  <div class="flex-grow-1">
    <template v-if="mode === 'interval'">
      <div class="d-flex ga-2 align-center">
        <v-select
          v-model="mode"
          :items="modeOptions"
          variant="outlined"
          density="compact"
          hide-details
          class="nf-mode"
        />
        <ClampedNumberField
          :model-value="fromValue"
          :min="min"
          :max="max"
          control-variant="stacked"
          density="compact"
          hide-details
          variant="outlined"
          class="flex-grow-1"
          style="min-width:0"
          @update:model-value="onFromChange"
        />
        <span class="text-medium-emphasis flex-shrink-0">—</span>
        <ClampedNumberField
          :model-value="toValue"
          :min="min"
          :max="max"
          control-variant="stacked"
          density="compact"
          hide-details
          variant="outlined"
          class="flex-grow-1"
          style="min-width:0"
          @update:model-value="onToChange"
        />
      </div>
    </template>
    <div v-else class="d-flex align-center ga-2">
      <v-select
        v-model="mode"
        :items="modeOptions"
        variant="outlined"
        density="compact"
        hide-details
        class="nf-mode"
      />
      <ClampedNumberField
        :model-value="innerValue"
        :min="min"
        :max="max"
        control-variant="stacked"
        density="compact"
        hide-details
        variant="outlined"
        class="flex-grow-1"
        @update:model-value="onInnerChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { FilterValue, NumberFilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'

const props = defineProps<{
  field: FilterField
  modelValue?: FilterValue
}>()

const emit = defineEmits<{
  'update:modelValue': [value: NumberFilterValue | undefined]
}>()

const mode = ref<'equals' | 'from' | 'to' | 'interval'>('equals')
const singleValue = ref<number>(0)
const fromValue = ref<number>(0)
const toValue = ref<number>(0)

const min = computed(() => props.field.meta?.min ?? 0)
const max = computed(() => props.field.meta?.max ?? 999999)

const modeOptions = [
  { title: 'Равно', value: 'equals' },
  { title: 'От', value: 'from' },
  { title: 'До', value: 'to' },
  { title: 'Интервал', value: 'interval' },
]

const innerValue = computed(() => {
  if (mode.value === 'equals') return singleValue.value
  if (mode.value === 'from') return fromValue.value
  return toValue.value
})

function parse(v: FilterValue | undefined) {
  if (!v || typeof v !== 'object') {
    if (typeof v === 'number' || typeof v === 'string') {
      mode.value = 'equals'
      singleValue.value = Number(v) || 0
    }
    return
  }
  mode.value = v.mode === 'from' ? 'from' : v.mode === 'to' ? 'to' : v.mode === 'interval' ? 'interval' : 'equals'
  if (v.mode === 'equals') {
    singleValue.value = Number(v.value) || 0
  } else if (v.mode === 'from') {
    fromValue.value = Number(v.from) || 0
  } else if (v.mode === 'to') {
    toValue.value = Number(v.to) || 0
  } else if (v.mode === 'interval') {
    fromValue.value = Number(v.from) || 0
    toValue.value = Number(v.to) || 0
  }
}

function emitValue() {
  if (mode.value === 'equals') {
    emit('update:modelValue', { mode: 'equals', value: singleValue.value })
  } else if (mode.value === 'from') {
    emit('update:modelValue', { mode: 'from', from: fromValue.value })
  } else if (mode.value === 'to') {
    emit('update:modelValue', { mode: 'to', to: toValue.value })
  } else {
    emit('update:modelValue', { mode: 'interval', from: fromValue.value, to: toValue.value })
  }
}

watch(mode, () => { emitValue() })

function onInnerChange(v: number) {
  if (mode.value === 'equals') singleValue.value = v
  else if (mode.value === 'from') fromValue.value = v
  else toValue.value = v
  emitValue()
}

function onFromChange(v: number) {
  fromValue.value = v
  emitValue()
}

function onToChange(v: number) {
  toValue.value = v
  emitValue()
}

watch(() => props.modelValue, (v) => {
  parse(v)
}, { immediate: true })
</script>

<style scoped>
.nf-mode {
  width: 140px;
  flex: none;
}
</style>
