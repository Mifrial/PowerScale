<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns, filterFields } from '@/modules/Core/User/Constant/groupsGridManifest';
import type { Row } from '@/modules/Core/UI/Dto/Row';
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue';
import { extractFilterValue } from '@/modules/Core/UI/Utils/filterExtract';

const router = useRouter();
const store = useGroupStore();
const { signal } = useAbortable();

const {
  sort,
  pagination,
  appliedFilters,
  pageRows,
  onSortChange,
  onPaginationChange,
  onFilterChange: gridFilterChange,
} = useGridPage(() => store.filteredGroups);

onMounted(() => store.fetchGroups(signal.value));

function onFilterChange(filters: Record<string, FilterValue>) {
  gridFilterChange(filters);
  store.filterName = extractFilterValue(filters.name);
  store.filterActive = filters.active !== undefined ? String(filters.active) : '';
}

function onRowAction(payload: { action: string; row: Row }) {
  if ('id' in payload.row) {
    const id = payload.row.id;
    if (payload.action === 'view-profile') {
      router.push(`/admin/groups/${id}`);
    }
  }
}
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Группы пользователей</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/admin/groups/new')"> Создать </v-btn>
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
