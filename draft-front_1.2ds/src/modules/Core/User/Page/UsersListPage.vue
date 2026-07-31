<template>
  <v-container>
    <h1 class="text-h5 mb-4">Пользователи</h1>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="users"
      @update:model-value="onFilterChange"
    />

    <SmartGrid
      class="mt-4"
      grid-id="users-list"
      :columns="columns"
      :rows="pageRows"
      :pagination="pagination"
      :total="store.filteredUsers.length"
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
import { useUserStore } from '@/modules/Core/User/Store/users'
import { useAbortable } from '@/modules/Core/Composables/useAbortable'
import FilterBar from '@/modules/Core/UI/Components/FilterBar.vue'
import SmartGrid from '@/modules/Core/UI/Components/Grid/SmartGrid.vue'
import type { FilterField } from '@/modules/Core/UI/Interfaces/FilterField'
import type { ColumnDefinition } from '@/modules/Core/UI/Interfaces/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Interfaces/Row'
import type { Sort } from '@/modules/Core/UI/Interfaces/Sort'
import type { Pagination } from '@/modules/Core/UI/Interfaces/Pagination'

const router = useRouter()
const store = useUserStore()
const { signal } = useAbortable()

const sort = ref<Sort | null>(null)
const pagination = ref<Pagination>({ page: 1, perPage: 10 })
const appliedFilters = ref<Record<string, any>>({})

onMounted(() => store.fetchUsers(signal.value))

const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Имя', type: 'string', meta: { clickable: true } },
  { key: 'surname', label: 'Фамилия', type: 'string' },
  { key: 'nickname', label: 'Псевдоним', type: 'string' },
  { key: 'login', label: 'Логин', type: 'string' },
  { key: 'email', label: 'Email', type: 'string' },
  { key: 'active', label: 'Активность', type: 'active', sortable: true },
  { key: 'lastLogin', label: 'Последний вход', type: 'date' },
]

const filterFields: FilterField[] = [
  { key: 'name', label: 'Имя', type: 'string' },
  { key: 'surname', label: 'Фамилия', type: 'string' },
  { key: 'nickname', label: 'Псевдоним', type: 'string' },
  { key: 'login', label: 'Логин', type: 'string' },
  { key: 'email', label: 'Email', type: 'string' },
  { key: 'active', label: 'Активность', type: 'active', options: [{ label: 'Активен', value: true }, { label: 'Неактивен', value: false }] },
  { key: 'lastLogin', label: 'Последний вход', type: 'datetime' },
]

function extractFilterValue(v: any): string {
  if (v === undefined || v === null) return ''
  if (typeof v === 'string') return v
  if (typeof v === 'boolean') return String(v)
  if (typeof v === 'object') {
    if (v.value !== undefined) return String(v.value)
  }
  return ''
}

function extractStringFilter(v: any): { mode: 'equals' | 'contains'; value: string } | null {
  if (v === undefined || v === null) return null
  if (typeof v === 'string') {
    return v ? { mode: 'contains', value: v } : null
  }
  if (typeof v === 'object' && v.value !== undefined) {
    return { mode: v.mode === 'equals' ? 'equals' : 'contains', value: String(v.value) }
  }
  return null
}

function onFilterChange(filters: Record<string, any>) {
  appliedFilters.value = filters
  store.quickFilter = extractFilterValue(filters.q)
  store.filterName = extractStringFilter(filters.name)
  store.filterSurname = extractStringFilter(filters.surname)
  store.filterNickname = extractStringFilter(filters.nickname)
  store.filterLogin = extractStringFilter(filters.login)
  store.filterEmail = extractStringFilter(filters.email)
  store.filterActive = extractFilterValue(filters.active)
  store.filterLastLogin = extractFilterValue(filters.lastLogin)
  pagination.value.page = 1
}

function onSortChange(s: Sort | null) {
  sort.value = s
}

function onPaginationChange(p: Pagination) {
  pagination.value = p
}

function onRowAction(payload: { action: string; row: Row }) {
  if (payload.action === 'view-profile' && 'id' in payload.row) {
    router.push(`/users/${payload.row.id}`)
  }
}

const sortedUsers = computed(() => {
  const s = sort.value
  if (!s) return store.filteredUsers
  const arr = [...store.filteredUsers]
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
    const cmp = String(va).localeCompare(String(vb))
    return s.order === 'asc' ? cmp : -cmp
  })
  return arr
})

const pageRows = computed(() => {
  const p = pagination.value
  const start = (p.page - 1) * p.perPage
  return sortedUsers.value.slice(start, start + p.perPage)
})
</script>
