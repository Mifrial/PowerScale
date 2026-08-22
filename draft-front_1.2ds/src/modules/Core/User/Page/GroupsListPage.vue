<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useGroupStore } from '@/modules/Core/User/Store/groups';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Core/User/Constant/Grid/groups/columns';
import { filterFields } from '@/modules/Core/User/Constant/Grid/groups/filterFields';

const router = useRouter();
const store = useGroupStore();
const { signal } = useAbortable();

const { sort, pagination, appliedFilters, pageRows, total, onSortChange, onPaginationChange, onFilterChange } =
  useGridPage({
    getItems: () => store.groups,
    fields: filterFields,
    columns,
  });

onMounted(() => store.fetchGroups(signal.value));

function onRowAction(payload: { action: string; row: Record<string, unknown> }) {
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
