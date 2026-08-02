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
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { useGridPage } from '@/modules/Core/Engine/Composables/useGridPage'
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue'
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Dto/Row'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import { extractFilterValue, extractStringFilter } from '@/modules/Core/UI/Utils/filterExtract'

const router = useRouter()
const store = useUserStore()
const { signal } = useAbortable()

const { sort, pagination, appliedFilters, pageRows, onSortChange, onPaginationChange, onFilterChange: gridFilterChange } =
  useGridPage(() => store.filteredUsers)

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

function onFilterChange(filters: Record<string, FilterValue>) {
  gridFilterChange(filters)
  store.quickFilter = extractFilterValue(filters.q)
  store.filterName = extractStringFilter(filters.name)
  store.filterSurname = extractStringFilter(filters.surname)
  store.filterNickname = extractStringFilter(filters.nickname)
  store.filterLogin = extractStringFilter(filters.login)
  store.filterEmail = extractStringFilter(filters.email)
  store.filterActive = extractFilterValue(filters.active)
  store.filterLastLogin = extractFilterValue(filters.lastLogin)
}

function onRowAction(payload: { action: string; row: Row }) {
  if (payload.action === 'view-profile' && 'id' in payload.row) {
    router.push(`/users/${payload.row.id}`)
  }
}
</script>
