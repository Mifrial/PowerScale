<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';
import TreeView from '@/modules/Core/UI/Component/Tree/TreeView.vue';
import { treeNodeService } from '@/modules/Core/UI/Service/Instance/treeNodeService';

const props = withDefaults(
  defineProps<{
    field: FilterField;
    modelValue?: FilterOptionValue | null;
    eager?: boolean;
  }>(),
  { eager: false },
);

const emit = defineEmits<{
  'update:modelValue': [value: FilterOptionValue | null | undefined];
}>();

const searchText = ref('');
const menuOpen = ref(false);
const expandedIds = ref<string[]>([]);

const selectedId = computed(() =>
  props.modelValue === null || props.modelValue === undefined ? null : String(props.modelValue),
);

const selectedLabel = computed(() => {
  const selected = (props.field.treeOptions ?? []).find((option) => option.value === props.modelValue);

  return selected?.path ?? '';
});

const treeNodes = computed(() =>
  treeNodeService.nest(
    (props.field.treeOptions ?? []).map((option, index) => ({
      id: String(option.value),
      label: option.label,
      parentId: option.parentValue === null || option.parentValue === undefined ? null : String(option.parentValue),
      sortOrder: index,
    })),
  ),
);

const visibleNodes = computed(() => treeNodeService.filter(treeNodes.value, searchText.value));

function defaultExpandedIds(): string[] {
  if (searchText.value.trim()) return treeNodeService.parentIds(visibleNodes.value);
  if (!selectedId.value) return [];

  return treeNodeService.ancestorIds(treeNodes.value, selectedId.value);
}

watch(menuOpen, (open) => {
  if (open) expandedIds.value = defaultExpandedIds();
});

watch(searchText, () => {
  expandedIds.value = defaultExpandedIds();
});

function selectOption(id: string): void {
  const option = (props.field.treeOptions ?? []).find((item) => String(item.value) === id);
  emit('update:modelValue', option?.value ?? id);
  menuOpen.value = false;
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
      :eager="eager"
      :scrim="false"
      scroll-strategy="none"
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
          <TreeView
            :nodes="visibleNodes"
            v-model:expanded-ids="expandedIds"
            :selected-id="selectedId"
            empty-text="Секции не найдены"
            @activate="selectOption"
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
