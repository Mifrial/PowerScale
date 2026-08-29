<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';

const props = defineProps<{
  field: FilterField;
  modelValue?: FilterOptionValue | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: FilterOptionValue | null | undefined];
}>();

const searchText = ref('');
const openedValues = ref<FilterOptionValue[]>([]);
const menuOpen = ref(false);

const selectedLabel = computed(() => {
  const selected = (props.field.treeOptions ?? []).find((option) => option.value === props.modelValue);

  return selected?.path ?? '';
});

const treeItems = computed(() => {
  const options = props.field.treeOptions ?? [];
  const query = searchText.value.trim().toLocaleLowerCase();
  const visibleValues = new Set<FilterOptionValue>();

  if (!query) {
    options.forEach((option) => visibleValues.add(option.value));
  } else {
    for (const option of options) {
      if (!`${option.label} ${option.path}`.toLocaleLowerCase().includes(query)) continue;
      visibleValues.add(option.value);
      let parentValue = option.parentValue;
      while (parentValue !== null && parentValue !== undefined) {
        visibleValues.add(parentValue);
        parentValue = options.find((candidate) => candidate.value === parentValue)?.parentValue;
      }
    }
  }

  const buildChildren = (parentValue: FilterOptionValue | null): unknown[] =>
    options
      .filter((option) => (option.parentValue ?? null) === parentValue && visibleValues.has(option.value))
      .map((option) => {
        const children = buildChildren(option.value);

        return children.length > 0
          ? { label: option.label, value: option.value, children }
          : { label: option.label, value: option.value };
      });

  return buildChildren(null);
});

function parentValues(value: FilterOptionValue | null | undefined): FilterOptionValue[] {
  const options = props.field.treeOptions ?? [];
  const values: FilterOptionValue[] = [];
  let parentValue = options.find((option) => option.value === value)?.parentValue;
  while (parentValue !== null && parentValue !== undefined) {
    values.unshift(parentValue);
    parentValue = options.find((option) => option.value === parentValue)?.parentValue;
  }

  return values;
}

function synchronizeOpenedValues(): void {
  const options = props.field.treeOptions ?? [];
  const query = searchText.value.trim().toLocaleLowerCase();
  const values = new Set<FilterOptionValue>();
  if (query) {
    for (const option of options) {
      if (`${option.label} ${option.path}`.toLocaleLowerCase().includes(query)) {
        parentValues(option.value).forEach((value) => values.add(value));
      }
    }
  } else {
    parentValues(props.modelValue).forEach((value) => values.add(value));
  }
  const nextValues = [...values];
  if (sameValues(openedValues.value, nextValues)) return;
  openedValues.value = nextValues;
}

function updateOpened(values: unknown[]): void {
  const nextValues = [
    ...new Set(
      values.filter(
        (value): value is FilterOptionValue =>
          typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean',
      ),
    ),
  ];
  if (sameValues(openedValues.value, nextValues)) return;
  openedValues.value = nextValues;
}

function sameValues(left: FilterOptionValue[], right: FilterOptionValue[]): boolean {
  return left.length === right.length && left.every((value) => right.includes(value));
}

watch([searchText, () => props.field.treeOptions], synchronizeOpenedValues, { immediate: true });

function updateSelection(selectedValues: unknown[]): void {
  const selectedValue = selectedValues[0];
  emit(
    'update:modelValue',
    typeof selectedValue === 'string' || typeof selectedValue === 'number' || typeof selectedValue === 'boolean'
      ? selectedValue
      : null,
  );
}

function clearSelection(): void {
  emit('update:modelValue', null);
  menuOpen.value = false;
}
</script>

<template>
  <div class="flex-grow-1" @click.stop>
    <v-menu
      v-model="menuOpen"
      :close-on-content-click="false"
      location="bottom start"
      :min-width="360"
      :max-width="420"
      elevation="8"
    >
      <template #activator="{ props: menuProps }">
        <v-text-field
          v-bind="menuProps"
          :model-value="selectedLabel"
          :label="field.label"
          placeholder="Выбрать секцию"
          density="compact"
          hide-details
          variant="outlined"
          readonly
          append-inner-icon="mdi-chevron-down"
        />
      </template>
      <div class="tree-select-popup pa-3 bg-surface">
        <v-text-field
          v-model="searchText"
          label="Поиск по секциям"
          density="compact"
          hide-details
          variant="outlined"
          clearable
          class="mb-3"
        />
        <div class="tree-select-tree">
          <v-treeview
            :items="treeItems"
            item-title="label"
            item-value="value"
            selectable
            select-strategy="single-independent"
            :selected="modelValue === null || modelValue === undefined ? [] : [modelValue]"
            :opened="openedValues"
            density="compact"
            open-on-click
            @update:opened="updateOpened"
            @update:selected="updateSelection"
          />
        </div>
        <v-btn
          v-if="modelValue !== null && modelValue !== undefined"
          variant="text"
          size="small"
          class="mt-2"
          @click="clearSelection"
        >
          Сбросить
        </v-btn>
      </div>
    </v-menu>
  </div>
</template>

<style scoped>
.tree-select-popup {
  box-shadow: 0 6px 18px rgb(0 0 0 / 22%);
}

.tree-select-tree {
  height: 320px;
  overflow-y: auto;
  overflow-x: hidden;
}
</style>
