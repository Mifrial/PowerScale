<template>
  <div v-bind="$attrs">
    <div class="smart-ears-wrapper">
      <div class="smart-ear smart-ear--left" v-show="earsLeft" @click="scrollEars(-1)">‹</div>
      <div class="smart-ear smart-ear--right" v-show="earsRight" @click="scrollEars(1)">›</div>
      <div class="smart-ears-scroll" ref="earsScrollRef" @mouseenter="updateEars">
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
            <tr>
              <th v-if="gridId" class="smart-header-th smart-header-th--settings" draggable="false">
                <v-icon size="small" class="smart-settings-icon" @click="settingsOpen = true">mdi-cog-outline</v-icon>
              </th>
              <th
                v-for="col in displayColumns"
                :key="col.key ?? ''"
                class="smart-header-th"
                :class="{
                  sorted: isColumnSorted(col.key),
                  'smart-header-th--dragging': dragKey === col.key,
                  'drop-before': gridId && dropTarget?.key === col.key && dropTarget?.side === 'before',
                  'drop-after': gridId && dropTarget?.key === col.key && dropTarget?.side === 'after',
                  'smart-header-th--resizing': resizeKey === col.key,
                }"
                :style="colWidthStyle(col.key)"
                :draggable="!!gridId && resizeKey !== col.key"
                @click="toggleColumnSort(col.key)"
                @dragstart="onHeaderDragStart(col.key, $event)"
                @dragenter.prevent="onHeaderDragEnter(col.key, $event)"
                @dragover.prevent
                @drop.prevent="onHeaderDrop(col.key, $event)"
                @dragend="onHeaderDragEnd"
              >
                <div class="d-inline-flex align-center ga-1" style="overflow:hidden">
                  <span class="smart-header-label">{{ col.label }}</span>
                  <v-icon v-if="isColumnSorted(col.key)" size="x-small">
                    {{ iconForSort(col.key) }}
                  </v-icon>
                </div>
                <div class="smart-resize-handle" @mousedown.stop="onResizeStart(col.key, $event)" />
              </th>
            </tr>
          </template>

          <template #item="{ item }">
            <tr>
              <td v-if="gridId" class="smart-cell--settings">
                <v-menu location="bottom start" offset="4">
                  <template #activator="{ props: menuProps }">
                    <v-icon v-bind="menuProps" size="small" class="smart-burger-icon">mdi-dots-vertical</v-icon>
                  </template>
                  <v-list density="compact" nav>
                    <v-list-item @click="emit('row-action', { action: 'view-profile', row: item })">
                      <template #prepend><v-icon size="small">mdi-account-outline</v-icon></template>
                      <v-list-item-title>Посмотреть профиль</v-list-item-title>
                    </v-list-item>
                  </v-list>
                </v-menu>
              </td>
              <td v-for="col in displayColumns" :key="col.key">
                <span
                  v-if="col.meta?.clickable"
                  class="smart-cell--clickable"
                  @click="emit('row-action', { action: 'view-profile', row: item })"
                >
                  <component :is="cellComponent(col.type)" :value="item[col.key]" :column="col" />
                </span>
                <component v-else :is="cellComponent(col.type)" :value="item[col.key]" :column="col" />
              </td>
            </tr>
          </template>

          <template #bottom>
            <div v-if="pagination" class="smart-grid-footer d-flex align-center justify-end ga-3 pa-2">
              <v-select
                :model-value="pagination.perPage"
                :items="perPageOptions"
                label="Записей"
                density="compact"
                hide-details
                variant="outlined"
                style="flex: none; width: 110px"
                @update:model-value="onPerPageChange"
              />
              <v-spacer />
              <v-pagination
                v-if="totalPages > 1"
                :model-value="pagination.page"
                :length="totalPages"
                density="compact"
                size="28"
                total-visible="5"
                @update:model-value="onPageChange"
              />
            </div>
          </template>
        </v-data-table>
      </div>

      <FieldPickerDialog
        v-if="gridId"
        v-model="settingsOpen"
        title="Настройка колонок"
        description="Отметьте колонки для отображения"
        :items="pickerItems"
        @apply="onSettingsApply"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import type { ColumnDefinition } from '@/modules/Core/UI/Interfaces/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Interfaces/Row'
import type { Sort } from '@/modules/Core/UI/Interfaces/Sort'
import type { Pagination } from '@/modules/Core/UI/Interfaces/Pagination'
import { getRenderer } from '@/modules/Core/UI/Components/Grid/cells/registry'
import StringCell from '@/modules/Core/UI/Components/Grid/cells/StringCell.vue'
import FieldPickerDialog from '@/modules/Core/UI/Components/FieldPickerDialog.vue'
import type { PickerItem } from '@/modules/Core/UI/Components/FieldPickerDialog.vue'
import { loadGridSettings, saveGridSettings, buildDisplayColumns } from '@/modules/Core/UI/Components/Grid/gridSettings'
import type { ColumnSetting } from '@/modules/Core/UI/Components/Grid/gridSettings'

const props = defineProps<{
  columns: ColumnDefinition[]
  rows: Row[]
  pagination?: Pagination | null
  total?: number
  sort?: Sort | null
  loading?: boolean
  gridId?: string
}>()

const emit = defineEmits<{
  'update:sort': [sort: Sort | null]
  'update:pagination': [pagination: Pagination]
  'row-action': [payload: { action: string; row: Row }]
}>()

const perPageOptions = [5, 10, 20, 50, 100]
const settingsOpen = ref(false)
const savedSettings = ref(loadGridSettings(props.gridId ?? ''))
const dragKey = ref<string | null>(null)
const dropTarget = ref<{ key: string; side: 'before' | 'after' } | null>(null)
const columnWidths = ref<Record<string, number>>(savedSettings.value?.widths ?? {})
const resizeKey = ref<string | null>(null)
const resizeStartX = ref(0)
const resizeStartWidth = ref(0)
const resizePerformed = ref(false)
const earsScrollRef = ref<HTMLElement | null>(null)
const earsLeft = ref(false)
const earsRight = ref(false)
const tableScrollEl = ref<HTMLElement | null>(null)

function updateEars() {
  const el = tableScrollEl.value
  if (!el) { earsLeft.value = false; earsRight.value = false; return }
  earsLeft.value = el.scrollLeft > 2
  earsRight.value = el.scrollLeft < el.scrollWidth - el.clientWidth - 2
}

function scrollEars(dir: -1 | 1) {
  const el = tableScrollEl.value
  if (!el) return
  el.scrollBy({ left: dir * 300, behavior: 'smooth' })
}

watch(() => props.gridId, (id) => {
  savedSettings.value = loadGridSettings(id ?? '')
  columnWidths.value = savedSettings.value?.widths ?? {}
})

function saveWidths() {
  if (!props.gridId) return
  const settings = savedSettings.value ?? { columns: [] }
  const updated = { ...settings, widths: { ...columnWidths.value } }
  saveGridSettings(props.gridId, updated)
  savedSettings.value = updated
}

function colWidthStyle(key: string): Record<string, string> {
  const w = columnWidths.value[key]
  return w ? { minWidth: `${w}px`, maxWidth: `${w}px` } : {}
}

function onResizeStart(key: string | null, e: MouseEvent) {
  if (!key) return
  e.preventDefault()
  resizeKey.value = key
  const th = (e.currentTarget as HTMLElement).parentElement as HTMLElement
  resizeStartX.value = e.clientX
  resizeStartWidth.value = th.offsetWidth
  document.addEventListener('mousemove', onResizeMove)
  document.addEventListener('mouseup', onResizeEnd)
}

function onResizeMove(e: MouseEvent) {
  if (!resizeKey.value) return
  const dx = e.clientX - resizeStartX.value
  if (Math.abs(dx) > 2) resizePerformed.value = true
  const w = Math.max(60, resizeStartWidth.value + dx)
  columnWidths.value = { ...columnWidths.value, [resizeKey.value]: w }
}

function onResizeEnd() {
  document.removeEventListener('mousemove', onResizeMove)
  document.removeEventListener('mouseup', onResizeEnd)
  if (resizeKey.value) {
    saveWidths()
  }
  resizeKey.value = null
  setTimeout(() => { resizePerformed.value = false }, 0)
}

const displayColumns = computed(() =>
  buildDisplayColumns(props.columns, savedSettings.value),
)

watch(displayColumns, () => nextTick(initEars), { immediate: true })
onMounted(() => {
  setTimeout(initEars, 500)
})
onBeforeUnmount(() => {
  if (tableScrollEl.value) {
    tableScrollEl.value.removeEventListener('scroll', updateEars)
  }
})

function initEars() {
  if (!earsScrollRef.value) return
  const el = earsScrollRef.value.querySelector('.v-table__wrapper') as HTMLElement | null
  if (!el || el === tableScrollEl.value) return
  if (tableScrollEl.value) {
    tableScrollEl.value.removeEventListener('scroll', updateEars)
  }
  tableScrollEl.value = el
  el.addEventListener('scroll', updateEars)

  const wrapper = earsScrollRef.value.closest('.smart-ears-wrapper') as HTMLElement | null
  if (wrapper) {
    const headerEl = earsScrollRef.value.querySelector('thead') as HTMLElement | null
    const footerEl = earsScrollRef.value.querySelector('.smart-grid-footer') as HTMLElement | null
    const top = headerEl?.offsetHeight ?? 44
    const bottom = footerEl?.offsetHeight ?? 0
    wrapper.style.setProperty('--ear-top', `${top + 2}px`)
    wrapper.style.setProperty('--ear-bottom', `${bottom + 2}px`)
  }

  updateEars()
}

const pickerItems = computed((): PickerItem[] => {
  const savedMap = new Map((savedSettings.value?.columns ?? []).map(s => [s.key, s.visible]))
  return props.columns.map(c => ({
    key: c.key,
    label: c.label,
    visible: savedMap.has(c.key) ? savedMap.get(c.key)! : true,
  }))
})

const totalItems = computed(() => props.total ?? props.rows.length)
const totalPages = computed(() => {
  if (!props.pagination?.perPage) return 1
  return Math.max(1, Math.ceil(totalItems.value / props.pagination.perPage))
})

const tableHeaders = computed(() =>
  displayColumns.value.map(c => ({
    title: c.label,
    key: c.key,
    sortable: c.sortable !== false,
    width: c.width,
  })),
)

function cellComponent(type: string) {
  return getRenderer(type) ?? StringCell
}

function onSettingsApply(items: PickerItem[]) {
  if (!props.gridId) return
  const columns: ColumnSetting[] = items.map(i => ({ key: i.key, visible: i.visible }))
  const settings = { columns, widths: { ...columnWidths.value } }
  saveGridSettings(props.gridId, settings)
  savedSettings.value = settings
}

function isColumnSorted(key: string | null) {
  return !!key && props.sort?.key === key
}

function iconForSort(key: string | null) {
  if (!key || props.sort?.key !== key) return ''
  return props.sort.order === 'asc' ? 'mdi-sort-ascending' : 'mdi-sort-descending'
}

function toggleColumnSort(key: string | null) {
  if (!key || resizePerformed.value) {
    resizePerformed.value = false
    return
  }
  const s = props.sort
  if (s?.key === key) {
    if (s.order === 'asc') {
      emit('update:sort', { key, order: 'desc' })
    } else {
      emit('update:sort', null)
    }
  } else {
    emit('update:sort', { key, order: 'asc' })
  }
  emit('update:pagination', { page: 1, perPage: props.pagination?.perPage ?? 10 })
}

function onPageChange(v: number) {
  if (props.pagination) {
    emit('update:pagination', { ...props.pagination, page: v })
  }
}

function onPerPageChange(v: number) {
  emit('update:pagination', { page: 1, perPage: v })
}

function saveDisplayOrder(keys: string[]) {
  if (!props.gridId) return
  const cols = props.columns.map(c => {
    const saved = savedSettings.value?.columns.find(s => s.key === c.key)
    return { key: c.key, visible: saved?.visible ?? true }
  })
  const orderMap = new Map(keys.map((k, i) => [k, i]))
  cols.sort((a, b) => (orderMap.get(a.key) ?? 999) - (orderMap.get(b.key) ?? 999))
  const settings = { columns: cols, widths: { ...columnWidths.value } }
  saveGridSettings(props.gridId, settings)
  savedSettings.value = settings
}

function onHeaderDragStart(key: string | null, e: DragEvent) {
  if (!key) return
  dragKey.value = key
  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
  }
  const el = e.currentTarget as HTMLElement
  const ghost = el.cloneNode(true) as HTMLElement
  ghost.style.cssText = `
    position: absolute; top: -9999px;
    background: #fff;
    box-shadow: 0 4px 16px rgba(0,0,0,0.18);
    border: 1px solid rgb(var(--v-theme-primary));
    border-radius: 4px;
    padding: 8px 16px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    white-space: nowrap;
    opacity: 1;
  `
  document.body.appendChild(ghost)
  if (e.dataTransfer) e.dataTransfer.setDragImage(ghost, 0, 0)
  requestAnimationFrame(() => { document.body.removeChild(ghost) })
}

function onHeaderDragEnter(key: string | null, e: DragEvent) {
  if (!key || !dragKey.value || dragKey.value === key) {
    dropTarget.value = null
    return
  }
  const th = (e.currentTarget as HTMLElement)
  const rect = th.getBoundingClientRect()
  const mid = rect.left + rect.width / 2
  dropTarget.value = { key, side: e.clientX < mid ? 'before' : 'after' }
}

function onHeaderDrop(key: string | null, _e: DragEvent) {
  if (!key || !dragKey.value || dragKey.value === key || !dropTarget.value) {
    dragKey.value = null
    dropTarget.value = null
    return
  }
  const display = displayColumns.value.map(c => c.key)
  const fromIdx = display.indexOf(dragKey.value)
  const toIdx = display.indexOf(key)
  if (fromIdx === -1 || toIdx === -1) {
    dragKey.value = null
    dropTarget.value = null
    return
  }
  display.splice(fromIdx, 1)
  const newToIdx = display.indexOf(key)
  const insertAt = dropTarget.value.side === 'before' ? newToIdx : newToIdx + 1
  display.splice(insertAt, 0, dragKey.value)
  saveDisplayOrder(display)
  dragKey.value = null
  dropTarget.value = null
}

function onHeaderDragEnd() {
  dragKey.value = null
  dropTarget.value = null
}
</script>

<style scoped>
.smart-grid :deep(table) {
  font-size: 0.875rem;
}
.smart-grid {
  border: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
}
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
  cursor: col-resize !important;
  user-select: none !important;
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
  padding: 0 4px !important;
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
.smart-cell--settings {
  width: 1%;
  padding: 0 !important;
  text-align: center;
}
.smart-cell--settings .smart-burger-icon {
  opacity: 0.5;
  cursor: pointer;
  transition: opacity 0.15s;
}
.smart-cell--settings .smart-burger-icon:hover {
  opacity: 1 !important;
}
.smart-cell--clickable {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
}
.smart-cell--clickable:hover {
  text-decoration: underline;
}
.smart-header-th:hover {
  opacity: 0.8;
}
.smart-header-th.sorted {
  color: rgb(var(--v-theme-primary));
}
.smart-grid-footer {
  border-top: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
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

.smart-ears-wrapper {
  position: relative;
  overflow: hidden;
}
.smart-ears-scroll {
  overflow-x: auto;
  scrollbar-width: thin;
}
.smart-ear {
  position: absolute;
  top: var(--ear-top, 44px);
  bottom: var(--ear-bottom, 44px);
  width: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-primary), 0.08);
  border: thin solid rgba(var(--v-theme-primary), 0.15);
  cursor: pointer;
  z-index: 2;
  padding: 0;
  transition: background 0.15s, opacity 0.15s;
  color: rgb(var(--v-theme-primary));
  font-size: 14px;
  user-select: none;
  backdrop-filter: blur(2px);
}
.smart-ear:hover {
  background: rgba(var(--v-theme-primary), 0.15);
}
.smart-ear--left {
  left: 0;
  border-radius: 0 8px 8px 0;
  border-left: none;
}
.smart-ear--right {
  right: 0;
  border-radius: 8px 0 0 8px;
  border-right: none;
}
</style>
