<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { DateTimeFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/DateTimeFilterValue';
import DateTimeInput from '@/modules/Core/UI/Component/Input/DateTimeInput.vue';
import { FILTER_RANGE_MODE_OPTIONS } from '@/modules/Core/UI/Constant/Filter/Modes/rangeOptions';

const props = defineProps<{
  field: FilterField;
  modelValue?: FilterValue;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: DateTimeFilterValue | undefined];
}>();

const mode = ref<'equals' | 'from' | 'to' | 'interval'>('equals');
const singleValue = ref<string>();
const fromValue = ref<string>();
const toValue = ref<string>();

const modeOptions = FILTER_RANGE_MODE_OPTIONS;

const innerValue = computed(() => {
  if (mode.value === 'equals') return singleValue.value;
  if (mode.value === 'from') return fromValue.value;

  return toValue.value;
});

function parse(v: FilterValue | undefined) {
  if (!v || typeof v !== 'object') {
    if (typeof v === 'string') {
      mode.value = 'equals';
      singleValue.value = v;
    }

    return;
  }
  mode.value = v.mode === 'from' ? 'from' : v.mode === 'to' ? 'to' : v.mode === 'interval' ? 'interval' : 'equals';
  if (v.mode === 'equals') {
    singleValue.value = typeof v.value === 'string' ? v.value : undefined;
  } else if (v.mode === 'from') {
    fromValue.value = typeof v.from === 'string' ? v.from : undefined;
  } else if (v.mode === 'to') {
    toValue.value = typeof v.to === 'string' ? v.to : undefined;
  } else if (v.mode === 'interval') {
    fromValue.value = typeof v.from === 'string' ? v.from : undefined;
    toValue.value = typeof v.to === 'string' ? v.to : undefined;
  }
}

function emitValue() {
  if (mode.value === 'equals') {
    emit('update:modelValue', singleValue.value ? { mode: 'equals', value: singleValue.value } : undefined);
  } else if (mode.value === 'from') {
    emit('update:modelValue', fromValue.value ? { mode: 'from', from: fromValue.value } : undefined);
  } else if (mode.value === 'to') {
    emit('update:modelValue', toValue.value ? { mode: 'to', to: toValue.value } : undefined);
  } else {
    const result: DateTimeFilterValue = { mode: 'interval' };
    if (fromValue.value) result.from = fromValue.value;
    if (toValue.value) result.to = toValue.value;
    emit('update:modelValue', result.from || result.to ? result : undefined);
  }
}

watch(mode, () => {
  if (mode.value === 'equals' && !singleValue.value) singleValue.value = '';
  if (mode.value === 'from' && !fromValue.value) fromValue.value = '';
  if (mode.value === 'to' && !toValue.value) toValue.value = '';
  emitValue();
});

function onInnerChange(v: string | undefined) {
  if (mode.value === 'equals') singleValue.value = v;
  else if (mode.value === 'from') fromValue.value = v;
  else toValue.value = v;
  emitValue();
}

function onFromChange(v: string | undefined) {
  fromValue.value = v;
  emitValue();
}

function onToChange(v: string | undefined) {
  toValue.value = v;
  emitValue();
}

watch(
  () => props.modelValue,
  (v) => {
    parse(v);
  },
  { immediate: true },
);
</script>

<template>
  <div class="flex-grow-1">
    <template v-if="mode === 'interval'">
      <div class="d-flex ga-2">
        <v-select
          v-model="mode"
          :items="modeOptions"
          variant="outlined"
          density="compact"
          hide-details
          class="dtf-mode"
        />
        <div class="flex-grow-1" style="min-width: 0">
          <DateTimeInput :field="field" :model-value="fromValue" @update:model-value="onFromChange" />
        </div>
        <div class="flex-grow-1" style="min-width: 0">
          <DateTimeInput :field="field" :model-value="toValue" @update:model-value="onToChange" />
        </div>
      </div>
    </template>
    <div v-else class="d-flex align-center ga-2">
      <v-select
        v-model="mode"
        :items="modeOptions"
        variant="outlined"
        density="compact"
        hide-details
        class="dtf-mode"
      />
      <DateTimeInput :field="field" :model-value="innerValue" class="flex-grow-1" @update:model-value="onInnerChange" />
    </div>
  </div>
</template>

<style scoped>
.dtf-mode {
  width: 140px;
  flex: none;
}
</style>
