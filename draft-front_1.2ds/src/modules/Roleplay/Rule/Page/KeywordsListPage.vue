<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Признаки</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/admin/keywords/new')">
        Создать
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="keywords"
      @update:model-value="onFilterChange"
    />

    <SmartGrid
      class="mt-4"
      grid-id="keywords-list"
      :columns="columns"
      :rows="pageRows"
      :pagination="pagination"
      :total="store.filteredTags.length"
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
import { useKeywordStore } from '../Store/keywords'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import { useGridPage } from '@/modules/Core/Engine/Composables/useGridPage'
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue'
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { Row } from '@/modules/Core/UI/Dto/Row'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import { extractFilterValue } from '@/modules/Core/UI/Utils/filterExtract'

const router = useRouter()
const store = useKeywordStore()
const { signal } = useAbortable()

const { sort, pagination, appliedFilters, pageRows, onSortChange, onPaginationChange, onFilterChange: gridFilterChange } =
  useGridPage(() => store.filteredTags)

onMounted(() => store.fetchTags(signal.value))

const columns: ColumnDefinition[] = [
  { key: 'code', label: 'Код', type: 'string' },
  { key: 'name', label: 'Название', type: 'string', meta: { clickable: true } },
  { key: 'description', label: 'Описание', type: 'string' },
  {
    key: 'active',
    label: 'Статус',
    type: 'boolean',
    meta: {
      trueLabel: 'Активен',
      falseLabel: 'Удалён',
      trueIcon: 'mdi-check-circle',
      falseIcon: 'mdi-cancel',
      trueColor: 'success',
      falseColor: 'grey',
    },
  },
]

const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'active', label: 'Статус', type: 'select', options: [{ label: 'Активен', value: true }, { label: 'Удалён', value: false }] },
]

function onFilterChange(filters: Record<string, FilterValue>) {
  gridFilterChange(filters)
  store.filterName = extractFilterValue(filters.name)
  store.filterActive = filters.active !== undefined ? String(filters.active) : ''
}

function onRowAction(payload: { action: string; row: Row }) {
  if ('id' in payload.row) {
    const id = payload.row.id
    if (payload.action === 'view-profile') {
      router.push(`/admin/keywords/${id}/edit`)
    }
  }
}
</script>
