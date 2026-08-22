<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Core/User/Constant/Grid/users/columns';
import { filterFields } from '@/modules/Core/User/Constant/Grid/users/filterFields';

const router = useRouter();
const store = useUserStore();
const { signal } = useAbortable();

const { sort, pagination, appliedFilters, pageRows, total, onSortChange, onPaginationChange, onFilterChange } =
  useGridPage({
    getItems: () => store.users,
    fields: filterFields,
    columns,
    searchFields: ['name', 'surname', 'nickname', 'login', 'email'],
  });

onMounted(() => store.fetchUsers(signal.value));

function onRowAction(payload: { action: string; row: Record<string, unknown> }) {
  const isOpen = payload.action === 'open' || payload.action === 'view-profile';
  if (isOpen && 'id' in payload.row) {
    router.push(`/users/${payload.row.id}`);
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
      :total="total"
      :sort="sort"
      :loading="store.loading"
      :row-menu="[{ action: 'view-profile', label: 'Посмотреть профиль', icon: 'mdi-account-outline' }]"
      @update:sort="onSortChange"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>
