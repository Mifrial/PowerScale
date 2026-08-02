<template>
  <div
    ref="barRef"
    class="filter-bar d-flex align-center"
    :class="{ 'filter-bar--open': menuOpen }"
    @click="menuOpen = !menuOpen"
  >
    <div class="filter-bar__chips d-flex ga-1 flex-wrap">
      <v-chip
        v-for="chip in activeChips"
        :key="chip.key"
        size="x-small"
        closable
        @click:close.stop="removeChip(chip.key)"
      >
        {{ chip.label }}
      </v-chip>
    </div>
    <input
      ref="inputRef"
      v-model="searchText"
      :placeholder="activeChips.length ? '' : (placeholder || 'Фильтр + поиск')"
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
      max-width="420"
    >

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
            @click.stop="settingsOpen = true"
          />
        </div>
        <div class="filter-fields">
          <div v-for="f in visibleFields" :key="f.key" class="filter-field d-flex align-center ga-6">
            <component
              :is="handlerComponent(f.type)"
              :field="f"
              :model-value="editBuffer[f.key]"
              class="flex-grow-1"
              @update:model-value="onValueUpdate(f.key, $event)"
            />
            <v-switch
              v-model="enabled[f.key]"
              density="compact"
              hide-details
              color="primary"
              class="flex-shrink-0"
            />
          </div>
        </div>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-3 pt-1">
        <v-btn variant="text" size="small" color="medium-emphasis" @click="resetAll">Сбросить</v-btn>
        <v-spacer />
        <v-btn variant="tonal" size="small" color="primary" @click="apply">Применить</v-btn>
      </v-card-actions>

      <FieldPickerDialog
        v-if="settingsKey"
        v-model="settingsOpen"
        title="Настройка полей фильтра"
        description="Отметьте поля, которые должны отображаться в фильтре"
        :items="pickerItems"
        @apply="onSettingsApply"
      />
    </v-card>
  </v-menu>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, type Component } from 'vue'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import { getFilterHandler } from './FilterBar/registry'
import FieldPickerDialog from '@/modules/Core/UI/Component/FieldPickerDialog.vue'
import type { PickerItem } from '@/modules/Core/UI/Component/FieldPickerDialog.vue'
import { loadFilterSettings, saveFilterSettings, buildVisibleFields, type FilterFieldSetting } from './FilterBar/filterSettings'
import { debounce } from '@/modules/Core/UI/Utils/debounce'

const props = defineProps<{
  fields: FilterField[]
  modelValue: Record<string, FilterValue>
  placeholder?: string
  settingsKey?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, FilterValue>]
}>()

const barRef = ref<HTMLDivElement>()
const menuOpen = ref(false)
const inputRef = ref<HTMLInputElement>()
const settingsOpen = ref(false)

const internal = reactive<Record<string, FilterValue | null | undefined>>({})
const editBuffer = reactive<Record<string, FilterValue | null | undefined>>({})
const enabled = reactive<Record<string, boolean>>({})

const savedSettings = ref(loadFilterSettings(props.settingsKey ?? ''))
const savedFieldSettings = ref<FilterFieldSetting[]>([])

watch(() => props.settingsKey, (key) => {
  savedSettings.value = loadFilterSettings(key ?? '')
})

watch(savedSettings, (s) => {
  savedFieldSettings.value = s?.fields ?? []
}, { immediate: true })

const visibleFields = computed(() =>
  buildVisibleFields(props.fields, savedSettings.value),
)

const pickerItems = computed((): PickerItem[] => {
  const savedMap = new Map(savedFieldSettings.value.map(s => [s.key, s.visible]))
  return props.fields.map(f => ({
    key: f.key,
    label: f.label,
    visible: savedMap.has(f.key) ? savedMap.get(f.key)! : true,
  }))
})

watch(() => props.modelValue, (v) => {
  for (const [k, val] of Object.entries(v)) {
    internal[k] = val
  }
  for (const k of Object.keys(internal)) {
    if (!(k in v)) delete internal[k]
  }
}, { immediate: true })

function emitNow() {
  const out: Record<string, FilterValue> = {}
  for (const [k, v] of Object.entries(internal)) {
    if (v !== undefined && v !== null && v !== '') {
      out[k] = v as FilterValue
    }
  }
  emit('update:modelValue', out)
}

const debouncedEmit = debounce(emitNow, 200)

const searchText = computed({
  get: () => (typeof internal.q === 'string' ? internal.q : ''),
  set: (v: string) => {
    internal.q = v || undefined
    debouncedEmit()
    // Закрываем попап при вводе текста
    if (v && menuOpen.value) {
      menuOpen.value = false
    }
  },
})

function isActive(field: FilterField): boolean {
  if (field.type === 'boolean') return internal[field.key] === true
  const v = internal[field.key]
  if (v === undefined || v === null) return false
  if (typeof v === 'string') return v !== ''
  if (typeof v === 'object') {
    if (v.mode === 'equals' || v.mode === 'contains') return !!v.value
    if (v.mode === 'from') return v.from !== undefined && v.from !== null
    if (v.mode === 'to') return v.to !== undefined && v.to !== null
    if (v.mode === 'interval') return v.from !== undefined || v.to !== undefined
    return true
  }
  return true
}

function formatDatetime(iso: string): string {
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return iso
    return d.toLocaleDateString('ru-RU', {
      day: '2-digit', month: '2-digit', year: 'numeric',
    }) + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  } catch { return iso }
}

function chipLabel(field: FilterField): string {
  if (field.type === 'boolean') return field.label
  const v = internal[field.key]
  if (typeof v === 'object' && v !== null) {
    const dt = field.type === 'datetime' || field.type === 'date'
    if (v.mode === 'contains') return `${field.label}: содержит "${v.value}"`
    if (v.mode === 'equals' || v.mode === undefined) return `${field.label}: ${dt ? formatDatetime(String(v.value ?? '')) : v.value ?? ''}`
    if (v.mode === 'from') return `${field.label}: с ${dt ? formatDatetime(String(v.from ?? '')) : v.from ?? '...'}`
    if (v.mode === 'to') return `${field.label}: до ${dt ? formatDatetime(String(v.to ?? '')) : v.to ?? '...'}`
    if (v.mode === 'interval') {
      const parts: string[] = []
      if (v.from !== undefined && v.from !== null) parts.push(`с ${dt ? formatDatetime(String(v.from)) : v.from}`)
      if (v.to !== undefined && v.to !== null) parts.push(`до ${dt ? formatDatetime(String(v.to)) : v.to}`)
      return `${field.label}: ${parts.join(' ')}`
    }
    return `${field.label}: ${JSON.stringify(v)}`
  }
  if (typeof v === 'string' && (field.type === 'datetime' || field.type === 'date')) {
    return `${field.label}: ${formatDatetime(v)}`
  }
  if ((field.type === 'select' || field.type === 'active') && field.options) {
    const option = field.options.find(opt => opt.value === v)
    if (option) return `${field.label}: ${option.label}`
  }
  return `${field.label}: ${v}`
}

const hasActiveFilters = computed(() => activeChips.value.length > 0 || !!internal.q)

const activeChips = computed(() =>
  props.fields
    .filter(f => isActive(f))
    .map(f => ({
      key: f.key,
      label: chipLabel(f),
    })),
)

function handlerComponent(type: string): Component | undefined {
  return getFilterHandler(type)?.component
}

function removeChip(key: string) {
  delete internal[key]
  emitNow()
}

function onValueUpdate(key: string, value: FilterValue | null | undefined) {
  editBuffer[key] = value
  enabled[key] = true
}

watch(menuOpen, (open) => {
  if (open) {
    for (const f of props.fields) {
      const val = internal[f.key]
      editBuffer[f.key] = val ?? (f.type === 'select' ? null : '')
      enabled[f.key] = val !== undefined && val !== null && val !== ''
    }
  }
})

function apply() {
  for (const f of props.fields) {
    if (f.type === 'boolean') {
      if (editBuffer[f.key]) { internal[f.key] = true } else { delete internal[f.key] }
    } else if (enabled[f.key]) {
      const val = editBuffer[f.key]
      if (val !== undefined && val !== null && val !== '') {
        internal[f.key] = val
      } else {
        delete internal[f.key]
      }
    } else {
      delete internal[f.key]
    }
  }
  emitNow()
  menuOpen.value = false
}

function resetAll() {
  for (const f of props.fields) {
    editBuffer[f.key] = ''
    enabled[f.key] = false
    delete internal[f.key]
  }
  internal.q = undefined
  emitNow()
  menuOpen.value = false
}

function onSettingsApply(items: PickerItem[]) {
  const settings: FilterFieldSetting[] = items.map(i => ({ key: i.key, visible: i.visible }))
  savedFieldSettings.value = settings
  const key = props.settingsKey
  if (key) {
    saveFilterSettings(key, { fields: settings })
    savedSettings.value = loadFilterSettings(key)
  }
}
</script>

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
.filter-bar__chips {
  align-items: center;
  max-width: 70%;
  overflow: hidden;
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
