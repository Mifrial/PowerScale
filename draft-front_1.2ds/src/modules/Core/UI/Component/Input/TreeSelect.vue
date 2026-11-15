<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { TreeSelectItem } from '@/modules/Core/UI/Dto/Tree/TreeSelectItem';
import TreeView from '@/modules/Core/UI/Component/Tree/TreeView.vue';
import { treeNodeService } from '@/modules/Core/UI/Service/Instance/treeNodeService';

const props = withDefaults(
  defineProps<{
    modelValue: string | number | null;
    items: TreeSelectItem[];
    label?: string;
    placeholder?: string;
    emptyText?: string;
    searchLabel?: string;
    /** Показывать «Сбросить», когда значение выбрано. */
    clearable?: boolean;
    disabled?: boolean;
    readonly?: boolean;
    /** Рендерить меню сразу (быстрый первый клик в плотных формах). */
    eager?: boolean;
    density?: 'default' | 'comfortable' | 'compact';
  }>(),
  {
    label: '',
    placeholder: 'Выбрать',
    emptyText: 'В дереве нет элементов',
    searchLabel: 'Поиск по дереву',
    clearable: false,
    disabled: false,
    readonly: false,
    eager: false,
    density: undefined,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string | number | null];
}>();

const searchText = ref('');
const menuOpen = ref(false);
const expandedIds = ref<string[]>([]);

const itemsByValue = computed(() => new Map(props.items.map((item) => [String(item.value), item])));

const selectedId = computed(() => (props.modelValue === null ? null : String(props.modelValue)));

/** Путь выбранного узла: «История → Войны». */
const selectedLabel = computed(() => {
  const id = selectedId.value;
  if (id === null) return '';
  const parts: string[] = [];
  const visited = new Set<string>();
  let current = itemsByValue.value.get(id) ?? null;
  while (current && !visited.has(String(current.value))) {
    visited.add(String(current.value));
    parts.unshift(current.label);
    current =
      current.parentValue === null || current.parentValue === undefined
        ? null
        : (itemsByValue.value.get(String(current.parentValue)) ?? null);
  }

  return parts.join(' → ');
});

const treeNodes = computed(() =>
  treeNodeService.nest(
    props.items.map((item, index) => ({
      id: String(item.value),
      label: item.label,
      parentId: item.parentValue === null || item.parentValue === undefined ? null : String(item.parentValue),
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
  const item = itemsByValue.value.get(id);
  emit('update:modelValue', item ? item.value : id);
  menuOpen.value = false;
}

function clearSelection(): void {
  emit('update:modelValue', null);
  menuOpen.value = false;
}
</script>

<template>
  <v-menu
    v-model="menuOpen"
    :close-on-content-click="false"
    :disabled="props.disabled || props.readonly"
    :scrim="false"
    :eager="props.eager"
    scroll-strategy="none"
    location="bottom start"
    :min-width="360"
    :max-width="440"
    elevation="8"
  >
    <template #activator="{ props: menuProps }">
      <v-text-field
        v-bind="menuProps"
        :model-value="selectedLabel"
        :label="props.label"
        :placeholder="props.placeholder"
        :density="props.density"
        :disabled="props.disabled"
        :readonly="props.readonly"
        hide-details="auto"
        append-inner-icon="mdi-chevron-down"
        @click:append-inner="menuOpen = true"
      />
    </template>
    <div class="tree-select-popup pa-3 bg-surface">
      <v-text-field
        v-model="searchText"
        :label="props.searchLabel"
        density="compact"
        hide-details
        clearable
        prepend-inner-icon="mdi-magnify"
        class="mb-3"
      />
      <div class="tree-select-tree">
        <TreeView
          v-model:expanded-ids="expandedIds"
          :nodes="visibleNodes"
          :selected-id="selectedId"
          :empty-text="props.emptyText"
          @activate="selectOption"
        />
      </div>
      <v-btn
        v-if="props.clearable && selectedId !== null"
        variant="text"
        size="small"
        class="mt-2"
        @click="clearSelection"
      >
        Сбросить
      </v-btn>
    </div>
  </v-menu>
</template>

<style scoped>
.tree-select-popup {
  box-shadow: 0 6px 18px rgb(0 0 0 / 22%);
}

.tree-select-tree {
  max-height: 320px;
  overflow-y: auto;
  overflow-x: hidden;
}
</style>
