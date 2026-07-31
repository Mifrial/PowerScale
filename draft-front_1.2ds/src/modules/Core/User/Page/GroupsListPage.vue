<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Группы пользователей</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/admin/groups/new')">
        Создать
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="groups"
      @update:model-value="onFilterChange"
    />

    <SmartGrid
      class="mt-4"
      grid-id="groups-list"
      :columns="columns"
      :rows="pageRows"
      :pagination="pagination"
      :total="store.filteredGroups.length"
      :sort="sort"
      :loading="store.loading"
      @update:sort="onSortChange"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useGroupStore } from '../Store/groups'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import FilterBar from '@/modules/Core/UI/Components/FilterBar.vue'
import SmartGrid from '@/modules/Core/UI/Components/Grid/SmartGrid.vue'
import type { FilterField } from '@/modules/Core/UI/Interfaces/FilterField'
import type { ColumnDefinition } from '@/modules/Core/UI/Interfaces/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Interfaces/Row'
import type { Sort } from '@/modules/Core/UI/Interfaces/Sort'
import type { Pagination } from '@/modules/Core/UI/Interfaces/Pagination'

const router = useRouter()
const store = useGroupStore()
const { signal } = useAbortable()

const sort = ref<Sort | null>(null)
const pagination = ref<Pagination>({ page: 1, perPage: 10 })
const appliedFilters = ref<Record<string, any>>({})

onMounted(() => store.fetchGroups(signal.value))

const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Название', type: 'string', meta: { clickable: true } },
  { key: 'memberCount', label: 'Участники', type: 'number' },
  {
    key: 'active',
    label: 'Статус',
    type: 'boolean',
    meta: {
      trueLabel: 'Активна',
      falseLabel: 'Деактивирована',
      trueIcon: 'mdi-check-circle',
      falseIcon: 'mdi-cancel',
      trueColor: 'success',
      falseColor: 'grey',
    },
  },
]

const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'active', label: 'Статус', type: 'select', options: [{ label: 'Активна', value: true }, { label: 'Деактивирована', value: false }] },
]

function extractFilterValue(v: any): string {
  if (!v) return ''
  if (typeof v === 'string') return v
  if (typeof v === 'object' && v.value) return v.value
  return ''
}

function onFilterChange(filters: Record<string, any>) {
  appliedFilters.value = filters
  store.filterName = extractFilterValue(filters.name)
  store.filterActive = filters.active !== undefined ? String(filters.active) : ''
  pagination.value.page = 1
}

function onSortChange(s: Sort | null) {
  sort.value = s
}

function onPaginationChange(p: Pagination) {
  pagination.value = p
}

function onRowAction(payload: { action: string; row: Row }) {
  if ('id' in payload.row) {
    const id = payload.row.id
    if (payload.action === 'view-profile') {
      router.push(`/admin/groups/${id}`)
    }
  }
}

const sortedGroups = computed(() => {
  const s = sort.value
  if (!s) return store.filteredGroups
  const arr = [...store.filteredGroups]
  arr.sort((a, b) => {
    const va = a[s.key as keyof typeof a]
    const vb = b[s.key as keyof typeof b]
    if (va == null) return 1
    if (vb == null) return -1
    if (typeof va === 'string') {
      const cmp = va.localeCompare(String(vb))
      return s.order === 'asc' ? cmp : -cmp
    }
    if (typeof va === 'number') {
      return s.order === 'asc' ? va - Number(vb) : Number(vb) - va
    }
    return 0
  })
  return arr
})

const pageRows = computed(() => {
  const p = pagination.value
  const start = (p.page - 1) * p.perPage
  return sortedGroups.value.slice(start, start + p.perPage)
})
</script>
