<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Core/User/Constant/Grid/groups/columns';
import { filterFields } from '@/modules/Core/User/Constant/Grid/groups/filterFields';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import type { Sort } from '@/modules/Core/UI/Dto/Grid/Sort';
import type { Group } from '@/modules/Core/User/Dto/Group';

const router = useRouter();
const store = useGroupStore();
const { signal } = useAbortable();
const sort = ref<Sort | null>(null);
const pagination = ref<Pagination>({ page: 1, perPage: 10 });
const appliedFilters = ref<Record<string, FilterValue>>({});

const pageRows = computed(() => {
  const rows = [...store.groups];
  const currentSort = sort.value;
  if (!currentSort) return rows;

  return rows.sort((left, right) => {
    const leftValue = String(left[currentSort.key as keyof Group] ?? '');
    const rightValue = String(right[currentSort.key as keyof Group] ?? '');
    const cmp = leftValue.localeCompare(rightValue);

    return currentSort.order === 'asc' ? cmp : -cmp;
  });
});

function filterText(value: FilterValue | undefined): string | undefined {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object' && 'value' in value && typeof value.value === 'string') {
    return value.value;
  }

  return undefined;
}

async function loadPage(): Promise<void> {
  const q = filterText(appliedFilters.value.q)?.trim();
  const activeFilter = appliedFilters.value.active;
  await store.findPage(
    {
      limit: pagination.value.perPage,
      offset: (pagination.value.page - 1) * pagination.value.perPage,
      q: q || undefined,
      active: typeof activeFilter === 'boolean' ? activeFilter : undefined,
    },
    signal.value,
  );
}

watch(
  [pagination, appliedFilters],
  () => {
    void loadPage();
  },
  { immediate: true },
);

function onFilterChange(filters: Record<string, FilterValue>): void {
  appliedFilters.value = filters;
  pagination.value = { ...pagination.value, page: 1 };
}

function onPaginationChange(next: Pagination): void {
  pagination.value = next;
}

function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
  if ('id' in payload.row) {
    const id = payload.row.id;
    if (payload.action === 'open' || payload.action === 'view-profile') {
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
      :total="store.total"
      :sort="sort"
      :loading="store.loading"
      :row-menu="[{ action: 'view-profile', label: 'Посмотреть профиль', icon: 'mdi-account-outline' }]"
      @update:sort="sort = $event"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>
