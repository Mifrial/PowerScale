<script setup lang="ts">
import { LEVEL_TINT_COLORS } from '@/modules/Core/UI/Constant/LevelTint/LEVEL_TINT_COLORS';
import type { LevelTintItem } from '@/modules/Core/UI/Dto/LevelTintItem';

defineProps<{
  modelValue: string;
  items: LevelTintItem[];
  label: string;
  disabled?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

function tintOf(level: number): string {
  const index = Math.min(LEVEL_TINT_COLORS.length - 1, Math.max(0, level));

  return LEVEL_TINT_COLORS[index];
}
</script>

<template>
  <v-select
    :model-value="modelValue"
    :items="items"
    item-title="title"
    item-value="value"
    density="compact"
    variant="outlined"
    hide-details
    :label="label"
    :disabled="disabled"
    @update:model-value="emit('update:modelValue', String($event ?? ''))"
  >
    <template #selection="{ item }">
      <span class="level-tint-select__row">
        <span class="level-tint-select__dot" :style="{ backgroundColor: tintOf(item.raw.level) }" />
        <span>{{ item.raw.title }}</span>
      </span>
    </template>
    <template #item="{ props: itemProps, item }">
      <v-list-item v-bind="itemProps">
        <template #prepend>
          <span class="level-tint-select__dot" :style="{ backgroundColor: tintOf(item.raw.level) }" />
        </template>
      </v-list-item>
    </template>
  </v-select>
</template>

<style scoped>
.level-tint-select__row {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.level-tint-select__dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  flex: 0 0 auto;
}
</style>
