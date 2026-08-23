<script setup lang="ts">
import { ref, computed, watch, toRef } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import FilterPopup from '@/modules/Core/UI/Component/FilterBar/FilterPopup.vue';
import FilterChips from '@/modules/Core/UI/Component/FilterBar/FilterChips.vue';
import { useFilterBuffer } from '@/modules/Core/UI/Composables/useFilterBuffer';
import { loadFilterSettings } from '@/modules/Core/UI/Utils/filterSettings/loadFilterSettings';
import { saveFilterSettings } from '@/modules/Core/UI/Utils/filterSettings/saveFilterSettings';
import { buildVisibleFields } from '@/modules/Core/UI/Utils/filterSettings/buildVisibleFields';
import type { FilterFieldSetting } from '@/modules/Core/UI/Dto/Filter/FilterFieldSetting';
import type { PickerItem } from '@/modules/Core/UI/Dto/Field/PickerItem';

const props = defineProps<{
  fields: FilterField[];
  modelValue: Record<string, FilterValue>;
  placeholder?: string;
  settingsKey?: string;
  menuWidth?: string | number;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, FilterValue>];
}>();

const barRef = ref<HTMLDivElement>();
const menuOpen = ref(false);
const settingsOpen = ref(false);

const savedSettings = ref(loadFilterSettings(props.settingsKey ?? ''));
const savedFieldSettings = ref<FilterFieldSetting[]>([]);

watch(
  () => props.settingsKey,
  (key) => {
    savedSettings.value = loadFilterSettings(key ?? '');
  },
);

watch(
  savedSettings,
  (s) => {
    savedFieldSettings.value = s?.fields ?? [];
  },
  { immediate: true },
);

const visibleFields = computed(() => buildVisibleFields(props.fields, savedSettings.value));

const pickerItems = computed((): PickerItem[] => {
  const savedMap = new Map(savedFieldSettings.value.map((s) => [s.key, s.visible]));

  return props.fields.map((f) => ({
    key: f.key,
    label: f.label,
    visible: savedMap.get(f.key) ?? true,
  }));
});

const {
  searchText,
  activeChips,
  hasActiveFilters,
  editBuffer,
  enabled,
  onValueUpdate,
  setEnabled,
  removeChip,
  apply,
  resetAll,
} = useFilterBuffer({
  fields: toRef(props, 'fields'),
  modelValue: toRef(props, 'modelValue'),
  menuOpen,
  onCommit: (value) => emit('update:modelValue', value),
});

function onSettingsApply(items: PickerItem[]) {
  const settings: FilterFieldSetting[] = items.map((i) => ({ key: i.key, visible: i.visible }));
  savedFieldSettings.value = settings;
  const key = props.settingsKey;
  if (key) {
    saveFilterSettings(key, { fields: settings });
    savedSettings.value = loadFilterSettings(key);
  }
}
</script>

<template>
  <div
    ref="barRef"
    class="filter-bar d-flex align-center"
    :class="{ 'filter-bar--open': menuOpen }"
    @click="menuOpen = !menuOpen"
  >
    <FilterChips :chips="activeChips" @remove="removeChip" />

    <input
      v-model="searchText"
      :placeholder="activeChips.length ? '' : placeholder || 'Фильтр + поиск'"
      class="filter-bar__input"
      @click.stop
      @focus="menuOpen = true"
    />
    <v-icon
      v-if="hasActiveFilters"
      class="filter-bar__reset"
      icon="mdi-close-circle"
      size="small"
      color="medium-emphasis"
      @click.stop="resetAll"
    />
    <v-icon class="filter-bar__icon mx-1" icon="mdi-magnify" color="medium-emphasis" size="small" />

    <v-menu
      v-model="menuOpen"
      :activator="barRef"
      :close-on-content-click="false"
      :open-on-click="false"
      location="bottom start"
      :width="menuWidth"
      :max-width="menuWidth || 420"
      :z-index="2400"
    >
      <FilterPopup
        :fields="visibleFields"
        :edit-buffer="editBuffer"
        :enabled="enabled"
        :settings-key="settingsKey"
        :picker-items="pickerItems"
        :settings-open="settingsOpen"
        @update:settings-open="settingsOpen = $event"
        @update:value="onValueUpdate"
        @update:enabled="setEnabled"
        @apply="apply"
        @reset-all="resetAll"
        @settings-apply="onSettingsApply"
      />
    </v-menu>
  </div>
</template>

<style scoped>
.filter-bar {
  position: relative;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  min-height: 40px;
  padding: 2px 8px;
  cursor: pointer;
  transition: border-color 0.15s;
}
.filter-bar:hover {
  border-color: rgba(var(--v-border-color), 0.7);
}
.filter-bar--open {
  border-color: rgb(var(--v-theme-primary));
}
.filter-bar__input {
  border: none;
  outline: none;
  flex: 1;
  min-width: 80px;
  font-size: 0.875rem;
  background: transparent;
  padding: 4px 0;
}
.filter-bar__icon {
  flex-shrink: 0;
  pointer-events: none;
}
.filter-bar__reset {
  flex-shrink: 0;
  cursor: pointer;
}
</style>
