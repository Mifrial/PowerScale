<script setup lang="ts">
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage'
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue'
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue'
import { columns, filterFields } from '@/modules/Core/User/Constant/usersGridManifest'
import type { Row } from '@/modules/Core/UI/Dto/Row'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import { extractFilterValue, extractStringFilter } from '@/modules/Core/UI/Utils/filterExtract'

const router = useRouter()
const store = useUserStore()
const { signal } = useAbortable()

const { sort, pagination, appliedFilters, pageRows, onSortChange, onPaginationChange, onFilterChange: gridFilterChange } =
  useGridPage(() => store.filteredUsers)

onMounted(() => store.fetchUsers(signal.value))

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
