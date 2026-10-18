<script setup lang="ts">
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { zoneCostSpecService } from '@/modules/Roleplay/Rule/Service/Instance/zoneCostSpecService';

const props = defineProps<{
  modelValue: Record<string, number>;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, number>];
}>();

function commit(table: Record<string, number>): void {
  emit('update:modelValue', table);
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-1">Значение параметра → накопленная стоимость. Ключи — строки.</div>
    <div class="d-flex ga-2 align-center flex-wrap mb-1" v-for="valueKey in zoneCostSpecService.tableKeys(modelValue)" :key="valueKey">
      <v-text-field
        :model-value="valueKey"
        label="Значение"
        density="compact"
        hide-details
        style="max-width: 120px"
        @update:model-value="commit(zoneCostSpecService.patchTableKey(modelValue, valueKey, String($event ?? valueKey)))"
      />
      <ClampedNumberField
        :model-value="modelValue[valueKey] ?? 0"
        label="Стоимость"
        density="compact"
        hide-details
        style="min-width: 120px"
        @update:model-value="commit(zoneCostSpecService.patchTableCost(modelValue, valueKey, $event ?? 0))"
      />
      <v-btn icon size="small" variant="text" @click="commit(zoneCostSpecService.removeTableRow(modelValue, valueKey))">
        <v-icon>mdi-minus</v-icon>
      </v-btn>
    </div>
    <v-btn size="small" variant="text" @click="commit(zoneCostSpecService.addTableRow(modelValue))">
      <v-icon start>mdi-plus</v-icon>
      Строка
    </v-btn>
  </div>
</template>
