<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import type { RowMenuAction } from '@/modules/Core/UI/Dto/Grid/RowMenuAction';
import { loadGridSettings } from '@/modules/Core/UI/Utils/gridSettings/loadGridSettings';
import { saveGridSettings } from '@/modules/Core/UI/Utils/gridSettings/saveGridSettings';
import { buildDisplayColumns } from '@/modules/Core/UI/Utils/gridSettings/buildDisplayColumns';
import type { ColumnSetting } from '@/modules/Core/UI/Dto/Grid/ColumnSetting';
import FieldPickerDialog from '@/modules/Core/UI/Component/FieldPickerDialog.vue';
import type { PickerItem } from '@/modules/Core/UI/Dto/Field/PickerItem';
import GridHeader from '@/modules/Core/UI/Component/Grid/header/GridHeader.vue';
import GridRow from '@/modules/Core/UI/Component/Grid/GridRow.vue';
import GridFooter from '@/modules/Core/UI/Component/Grid/GridFooter.vue';
import ScrollEars from '@/modules/Core/UI/Component/ScrollEars.vue';
import { useColumnResize } from '@/modules/Core/UI/Composables/useColumnResize';
import { useColumnDrag } from '@/modules/Core/UI/Composables/useColumnDrag';

const props = defineProps<{
  columns: ColumnDefinition[];
  rows: Record<string, unknown>[];
  pagination?: Pagination | null;
  total?: number;
  sort?: Sort | null;
  loading?: boolean;
  gridId?: string;
  rowMenu?: RowMenuAction[];
}>();

const emit = defineEmits<{
  'update:sort': [sort: Sort | null];
  'update:pagination': [pagination: Pagination];
  'row-action': [payload: { action: string; row: Record<string, unknown> }];
}>();

const settingsOpen = ref(false);
const savedSettings = ref(loadGridSettings(props.gridId ?? ''));
const columnWidths = ref<Record<string, number>>(savedSettings.value?.widths ?? {});

watch(
  () => props.gridId,
  (id) => {
    savedSettings.value = loadGridSettings(id ?? '');
    columnWidths.value = savedSettings.value?.widths ?? {};
  },
);

const displayColumns = computed(() => buildDisplayColumns(props.columns, savedSettings.value));

const totalItems = computed(() => props.total ?? props.rows.length);

const pickerItems = computed((): PickerItem[] => {
  const savedMap = new Map((savedSettings.value?.columns ?? []).map((s) => [s.key, s.visible]));

  return props.columns.map((c) => ({
    key: c.key,
    label: c.label,
    visible: savedMap.get(c.key) ?? true,
  }));
});

const tableHeaders = computed(() =>
  displayColumns.value.map((c) => ({
    title: c.label,
    key: c.key,
    sortable: c.sortable !== false,
    width: c.width,
  })),
);

function saveWidths() {
  if (!props.gridId) return;
  const settings = savedSettings.value ?? { columns: [] };
  const updated = { ...settings, widths: { ...columnWidths.value } };
  saveGridSettings(props.gridId, updated);
  savedSettings.value = updated;
}

const {
  resizingKey,
  resizePerformed,
  start: onResizeStart,
} = useColumnResize({
  columnWidths,
  saveWidths,
});

function saveOrder(keys: string[]) {
  if (!props.gridId) return;
  const cols = props.columns.map((c) => {
    const saved = savedSettings.value?.columns.find((s) => s.key === c.key);

    return { key: c.key, visible: saved?.visible ?? true };
  });
  const orderMap = new Map(keys.map((k, i) => [k, i]));
  cols.sort((a, b) => (orderMap.get(a.key) ?? 999) - (orderMap.get(b.key) ?? 999));
  const settings = { columns: cols, widths: { ...columnWidths.value } };
  saveGridSettings(props.gridId, settings);
  savedSettings.value = settings;
}

const {
  dragKey,
  dropTarget,
  start: onDragStart,
  enter: onDragEnter,
  drop: onDrop,
  end: onDragEnd,
} = useColumnDrag({
  displayColumns,
  saveOrder,
});

function onColumnSort(key: string) {
  if (resizePerformed.value) {
    resizePerformed.value = false;

    return;
  }
  const s = props.sort;
  if (s?.key === key) {
    if (s.order === 'asc') {
      emit('update:sort', { key, order: 'desc' });
    } else {
      emit('update:sort', null);
    }
  } else {
    emit('update:sort', { key, order: 'asc' });
  }
  emit('update:pagination', { page: 1, perPage: props.pagination?.perPage ?? 10 });
}

function onSettingsApply(items: PickerItem[]) {
  if (!props.gridId) return;
  const columns: ColumnSetting[] = items.map((i) => ({ key: i.key, visible: i.visible }));
  const settings = { columns, widths: { ...columnWidths.value } };
  saveGridSettings(props.gridId, settings);
  savedSettings.value = settings;
}

function onPaginationChange(pagination: Pagination) {
  emit('update:pagination', pagination);
}

function onRowAction(payload: { action: string; row: Record<string, unknown> }) {
  emit('row-action', payload);
}
</script>

<template>
  <div v-bind="$attrs">
    <ScrollEars
      :refresh="displayColumns"
      scroll-selector=".v-table__wrapper"
      top-selector="thead"
      bottom-selector=".smart-grid-footer"
    >
      <v-data-table
        :headers="tableHeaders"
        :items="rows"
        items-per-page="-1"
        :loading="loading"
        hover
        density="compact"
        class="smart-grid"
        hide-default-footer
      >
        <template #headers>
          <GridHeader
            :grid-id="gridId"
            :columns="displayColumns"
            :sort="sort"
            :widths="columnWidths"
            :drag-key="dragKey"
            :drop-target="dropTarget"
            :resizing-key="resizingKey"
            @settings="settingsOpen = true"
            @sort="onColumnSort"
            @resize-start="onResizeStart"
            @drag-start="onDragStart"
            @drag-enter="onDragEnter"
            @drop="onDrop"
            @drag-end="onDragEnd"
          />
        </template>

        <template #item="{ item }">
          <GridRow
            :grid-id="gridId"
            :columns="displayColumns"
            :item="item"
            :row-menu="rowMenu"
            @row-action="onRowAction"
          />
        </template>

        <template #bottom>
          <GridFooter
            :pagination="pagination ?? null"
            :total-items="totalItems"
            @update:pagination="onPaginationChange"
          />
        </template>
      </v-data-table>
    </ScrollEars>

    <FieldPickerDialog
      v-if="gridId"
      v-model="settingsOpen"
      title="Настройка колонок"
      description="Отметьте колонки для отображения"
      :items="pickerItems"
      @apply="onSettingsApply"
    />
  </div>
</template>

<style scoped>
.smart-grid :deep(table) {
  font-size: 0.875rem;
}
.smart-grid {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
</style>
