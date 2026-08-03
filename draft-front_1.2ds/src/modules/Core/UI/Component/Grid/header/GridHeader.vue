<script setup lang="ts">
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { Sort } from '@/modules/Core/UI/Dto/Sort'
import type { ColumnDropTarget } from '@/modules/Core/UI/Composables/useColumnDrag'

const props = defineProps<{
  gridId?: string
  columns: ColumnDefinition[]
  sort?: Sort | null
  widths: Record<string, number>
  dragKey: string | null
  dropTarget: ColumnDropTarget | null
  resizingKey: string | null
}>()

const emit = defineEmits<{
  settings: []
  sort: [key: string]
  'resize-start': [key: string, e: MouseEvent]
  'drag-start': [key: string, e: DragEvent]
  'drag-enter': [key: string, e: DragEvent]
  drop: [key: string, e: DragEvent]
  'drag-end': []
}>()

function colWidthStyle(key: string): Record<string, string> {
  const w = props.widths[key]
  return w ? { minWidth: `${w}px`, maxWidth: `${w}px` } : {}
}

function isColumnSorted(key: string) {
  return props.sort?.key === key
}

function iconForSort(key: string) {
  if (props.sort?.key !== key) return ''
  return props.sort.order === 'asc' ? 'mdi-sort-ascending' : 'mdi-sort-descending'
}
</script>

<template>
  <tr>
    <th v-if="gridId" class="smart-header-th smart-header-th--settings" draggable="false">
      <v-icon size="small" class="smart-settings-icon" @click="emit('settings')">mdi-cog-outline</v-icon>
    </th>
    <th
      v-for="col in columns"
      :key="col.key"
      class="smart-header-th"
      :class="{
        sorted: isColumnSorted(col.key),
        'smart-header-th--dragging': dragKey === col.key,
        'drop-before': gridId && dropTarget?.key === col.key && dropTarget?.side === 'before',
        'drop-after': gridId && dropTarget?.key === col.key && dropTarget?.side === 'after',
        'smart-header-th--resizing': resizingKey === col.key,
      }"
      :style="colWidthStyle(col.key)"
      :draggable="!!gridId && resizingKey !== col.key"
      @click="emit('sort', col.key)"
      @dragstart="emit('drag-start', col.key, $event)"
      @dragenter.prevent="emit('drag-enter', col.key, $event)"
      @dragover.prevent
      @drop.prevent="emit('drop', col.key, $event)"
      @dragend="emit('drag-end')"
    >
      <div class="d-inline-flex align-center ga-1" style="overflow:hidden">
        <span class="smart-header-label">{{ col.label }}</span>
        <v-icon v-if="isColumnSorted(col.key)" size="x-small">
          {{ iconForSort(col.key) }}
        </v-icon>
      </div>
      <div class="smart-resize-handle" @mousedown.stop="emit('resize-start', col.key, $event)" />
    </th>
  </tr>
</template>

<style scoped>
.smart-header-th {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
  border-right: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  position: relative;
}
.smart-header-th:last-child {
  border-right: none;
}
.smart-header-th--resizing,
.smart-header-th--resizing * {
  cursor: col-resize;
  user-select: none;
}
.smart-resize-handle {
  position: absolute;
  top: 0;
  right: 0;
  bottom: 0;
  width: 8px;
  cursor: col-resize;
  z-index: 1;
}
.smart-resize-handle:hover {
  background: rgba(var(--v-theme-primary), 0.12);
}
.smart-header-label {
  overflow: hidden;
  text-overflow: ellipsis;
}
.smart-header-th--settings {
  border-right: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  width: 1%;
  padding: 0 4px;
  text-align: center;
  cursor: default;
}
.smart-header-th--settings:hover .smart-settings-icon {
  opacity: 1;
}
.smart-settings-icon {
  opacity: 0.35;
  transition: opacity 0.15s;
}
.smart-header-th:hover {
  opacity: 0.8;
}
.smart-header-th.sorted {
  color: rgb(var(--v-theme-primary));
}
.smart-header-th--dragging {
  opacity: 0.4;
}
.smart-header-th.drop-before {
  box-shadow: inset 3px 0 0 rgb(var(--v-theme-primary));
}
.smart-header-th.drop-after {
  box-shadow: inset -3px 0 0 rgb(var(--v-theme-primary));
}
</style>
