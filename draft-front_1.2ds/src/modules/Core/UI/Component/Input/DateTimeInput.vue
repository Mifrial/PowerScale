<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  field: FilterField;
  modelValue?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string | undefined];
}>();

const menu = ref(false);
const dateModel = ref(new Date());
const hoursModel = ref(0);
const minutesModel = ref(0);

function isoDate(d: Date): string {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function parseIso(iso: unknown): { date: Date; hours: number; minutes: number } {
  if (!iso || typeof iso !== 'string') return { date: new Date(), hours: 0, minutes: 0 };
  const parts = iso.split('T');
  const dateStr = parts[0];
  const time = parts.length > 1 ? parts[1].slice(0, 5) : '';
  const [h = '0', m = '0'] = time.split(':');
  const ymd = dateStr ? dateStr.split('-').map(Number) : [0, 0, 0];

  return {
    date: dateStr ? new Date(ymd[0], ymd[1] - 1, ymd[2]) : new Date(),
    hours: parseInt(h) || 0,
    minutes: parseInt(m) || 0,
  };
}

function combineIso(d: Date, h: number, m: number): string | undefined {
  const hs = String(h).padStart(2, '0');
  const ms = String(m).padStart(2, '0');

  return `${isoDate(d)}T${hs}:${ms}:00`;
}

watch(
  () => props.modelValue,
  (val) => {
    const { date, hours, minutes } = parseIso(val);
    dateModel.value = date;
    hoursModel.value = hours;
    minutesModel.value = minutes;
  },
  { immediate: true },
);

function onDateChange(d: Date) {
  dateModel.value = d;
  emit('update:modelValue', combineIso(d, hoursModel.value, minutesModel.value));
}

function onHoursChange(v: number) {
  hoursModel.value = v;
  emit('update:modelValue', combineIso(dateModel.value, v, minutesModel.value));
}

function onMinutesChange(v: number) {
  minutesModel.value = v;
  emit('update:modelValue', combineIso(dateModel.value, hoursModel.value, v));
}

function clear() {
  dateModel.value = new Date();
  hoursModel.value = 0;
  minutesModel.value = 0;
  emit('update:modelValue', undefined);
}

const displayValue = computed(() => {
  const d = dateModel.value;

  return (
    d.toLocaleDateString('ru-RU', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }) + ` ${String(hoursModel.value).padStart(2, '0')}:${String(minutesModel.value).padStart(2, '0')}`
  );
});
</script>

<template>
  <div class="flex-grow-1">
    <v-dialog v-model="menu" max-width="360">
      <template #activator="{ props }">
        <v-text-field
          v-bind="props"
          :model-value="displayValue"
          :label="field.label"
          prepend-inner-icon="mdi-calendar-clock"
          readonly
          density="compact"
          hide-details
          variant="outlined"
          clearable
          class="flex-grow-1"
          @click:clear="clear"
        />
      </template>

      <v-card class="dt-card pa-0">
        <v-card-text class="pa-2 d-flex flex-column ga-2">
          <v-date-picker
            :model-value="dateModel"
            locale="ru"
            hide-header
            weeks-in-month="dynamic"
            @update:model-value="onDateChange"
          />
          <div class="dt-time-row">
            <ClampedNumberField
              :model-value="hoursModel"
              :min="0"
              :max="23"
              control-variant="stacked"
              density="compact"
              hide-details
              variant="outlined"
              class="dt-hh"
              @update:model-value="onHoursChange"
            />
            <span class="dt-colon">:</span>
            <ClampedNumberField
              :model-value="minutesModel"
              :min="0"
              :max="59"
              control-variant="stacked"
              density="compact"
              hide-details
              variant="outlined"
              class="dt-mm"
              @update:model-value="onMinutesChange"
            />
          </div>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-2">
          <v-spacer />
          <v-btn variant="text" size="small" color="primary" @click="menu = false">Готово</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<style scoped>
.dt-card {
  width: fit-content;
  min-width: 290px;
}
.dt-time-row {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2px;
  padding: 0 8px 10px;
}
.dt-hh {
  width: 100px;
  flex: 0 0 auto;
}
.dt-mm {
  width: 100px;
  flex: 0 0 auto;
}
.dt-hh :deep(input) {
  text-align: center;
}
.dt-mm :deep(input) {
  text-align: center;
}
.dt-colon {
  font-size: 1rem;
  font-weight: 500;
  margin-top: -2px;
}
</style>
