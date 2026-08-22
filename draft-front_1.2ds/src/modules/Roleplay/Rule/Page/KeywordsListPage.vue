<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Roleplay/Rule/Constant/Grid/keywords/columns';
import { filterFields } from '@/modules/Roleplay/Rule/Constant/Grid/keywords/filterFields';

const router = useRouter();
const store = useKeywordStore();
const { signal } = useAbortable();

const { sort, pagination, appliedFilters, pageRows, total, onSortChange, onPaginationChange, onFilterChange } =
  useGridPage({
    getItems: () => store.keywords,
    fields: filterFields,
    columns,
  });

onMounted(() => store.fetchTags(signal.value));

function onRowAction(payload: { action: string; row: Record<string, unknown> }) {
  if ('id' in payload.row) {
    const id = payload.row.id;
    if (payload.action === 'open' || payload.action === 'view-profile') {
      router.push(`/admin/keywords/${id}/edit`);
    }
  }
}
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Признаки</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/admin/keywords/new')"> Создать </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="keywords"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="store.error" type="error" class="mt-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchTags(signal)">Повторить</v-btn>
      </template>
    </v-alert>

    <SmartGrid
      class="mt-4"
      grid-id="keywords-list"
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
