<script setup lang="ts">
import type { Component } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField';
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue';
import { getFilterHandler } from '@/modules/Core/UI/Component/FilterBar/registry';
import FieldPickerDialog from '@/modules/Core/UI/Component/FieldPickerDialog.vue';
import type { PickerItem } from '@/modules/Core/UI/Dto/PickerItem';

defineProps<{
  fields: FilterField[];
  editBuffer: Record<string, FilterValue | null | undefined>;
  enabled: Record<string, boolean>;
  settingsKey?: string;
  pickerItems: PickerItem[];
  settingsOpen: boolean;
}>();

defineEmits<{
  'update:settings-open': [v: boolean];
  'update:value': [key: string, value: FilterValue | null | undefined];
  'update:enabled': [key: string, value: boolean];
  apply: [];
  'reset-all': [];
  'settings-apply': [items: PickerItem[]];
}>();

function handlerComponent(type: string): Component | undefined {
  return getFilterHandler(type)?.component;
}
</script>

<template>
  <v-card variant="outlined" class="filter-popup" rounded="lg">
    <v-card-text class="d-flex flex-column ga-0 pa-3">
      <div class="d-flex align-center mb-1">
        <span class="text-caption text-medium-emphasis">Поля фильтра</span>
        <v-spacer />
        <v-btn
          v-if="settingsKey"
          icon="mdi-cog"
          variant="text"
          size="x-small"
          color="medium-emphasis"
          @click.stop="$emit('update:settings-open', true)"
        />
      </div>
      <div class="filter-fields">
        <div v-for="f in fields" :key="f.key" class="filter-field d-flex align-center ga-6">
          <component
            :is="handlerComponent(f.type)"
            :field="f"
            :model-value="editBuffer[f.key]"
            class="flex-grow-1"
            @update:model-value="$emit('update:value', f.key, $event)"
          />
          <v-switch
            :model-value="enabled[f.key]"
            density="compact"
            hide-details
            color="primary"
            class="flex-shrink-0"
            @update:model-value="$emit('update:enabled', f.key, !!$event)"
          />
        </div>
      </div>
    </v-card-text>

    <v-divider />

    <v-card-actions class="pa-3 pt-1">
      <v-btn variant="text" size="small" color="medium-emphasis" @click="$emit('reset-all')">Сбросить</v-btn>
      <v-spacer />
      <v-btn variant="tonal" size="small" color="primary" @click="$emit('apply')">Применить</v-btn>
    </v-card-actions>

    <FieldPickerDialog
      v-if="settingsKey"
      :model-value="settingsOpen"
      title="Настройка полей фильтра"
      description="Отметьте поля, которые должны отображаться в фильтре"
      :items="pickerItems"
      @update:model-value="$emit('update:settings-open', $event)"
      @apply="$emit('settings-apply', $event)"
    />
  </v-card>
</template>

<style scoped>
.filter-popup {
  min-width: 300px;
  max-height: 360px;
  display: flex;
  flex-direction: column;
}
.filter-popup :deep(.v-card-text) {
  flex: 1 1 auto;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  min-height: 0;
}
.filter-fields {
  flex: 1 1 auto;
  overflow-y: auto;
  min-height: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 5px;
}
</style>
