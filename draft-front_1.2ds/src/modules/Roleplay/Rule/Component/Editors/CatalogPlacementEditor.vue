<script setup lang="ts">
import { computed } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import TreeSelectFilter from '@/modules/Core/UI/Component/FilterBar/handlers/TreeSelectFilter.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';

const props = defineProps<{
  catalogSection: string | null;
  catalogSortOrder: number;
  sections: { code: string; name: string; parentCode: string | null; sortOrder: number }[];
}>();

const emit = defineEmits<{
  'update:catalogSection': [value: string | null];
  'update:catalogSortOrder': [value: number];
}>();

const sectionOptions = computed(() => {
  const options: { label: string; value: string; path: string; depth: number; parentValue: string | null }[] = [];
  const childrenByParent = new Map<string | null, typeof props.sections>();
  for (const section of props.sections) {
    const children = childrenByParent.get(section.parentCode) ?? [];
    children.push(section);
    childrenByParent.set(section.parentCode, children);
  }
  const visit = (parentCode: string | null, depth: number, parentPath: string): void => {
    for (const section of (childrenByParent.get(parentCode) ?? []).sort(
      (left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code),
    )) {
      const path = parentPath ? `${parentPath} → ${section.name}` : section.name;
      options.push({ label: section.name, value: section.code, path, depth, parentValue: parentCode });
      visit(section.code, depth + 1, path);
    }
  };
  visit(null, 0, '');

  return options;
});

const sectionField = computed<FilterField>(() => ({
  key: 'catalogSection',
  label: 'Секция каталога',
  type: 'tree-select',
  treeOptions: sectionOptions.value,
}));

const panelTitle = computed(() => {
  const section = sectionOptions.value.find((option) => option.value === props.catalogSection);

  return section ? `Каталог : ${section.path}` : 'Каталог';
});
</script>

<template>
  <v-expansion-panel value="catalog">
    <v-expansion-panel-title>{{ panelTitle }}</v-expansion-panel-title>
    <v-expansion-panel-text>
      <ClampedNumberField
        :model-value="catalogSortOrder"
        label="Порядок в секции"
        :min="0"
        density="compact"
        hide-details
        class="mb-4"
        @update:model-value="emit('update:catalogSortOrder', $event)"
      />
      <TreeSelectFilter
        :field="sectionField"
        :model-value="catalogSection"
        @update:model-value="(value) => emit('update:catalogSection', typeof value === 'string' ? value : null)"
      />
    </v-expansion-panel-text>
  </v-expansion-panel>
</template>
