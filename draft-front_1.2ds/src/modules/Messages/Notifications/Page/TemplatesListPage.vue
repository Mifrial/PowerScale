<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useTemplateStore } from '@/modules/Messages/Notifications/Store/templates';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useGridPage } from '@/modules/Core/UI/Composables/useGridPage';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Messages/Notifications/Constant/Grid/templates/columns';
import { filterFields } from '@/modules/Messages/Notifications/Constant/Grid/templates/filterFields';

const router = useRouter();
const store = useTemplateStore();
const { signal } = useAbortable();

const { sort, pagination, appliedFilters, pageRows, total, onSortChange, onPaginationChange, onFilterChange } =
  useGridPage({
    getItems: () => store.templates,
    fields: filterFields,
    columns,
  });

onMounted(() => store.fetchTemplates(signal.value));

function onRowAction(payload: { action: string; row: Record<string, unknown> }) {
  if (payload.action !== 'open' && payload.action !== 'edit') return;
  if (!('id' in payload.row)) return;
  router.push(`/admin/notification-templates/${payload.row.id}/edit`);
}
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Шаблоны уведомлений</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/admin/notification-templates/new')">
        Создать
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="templates"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="store.error" type="error" class="mt-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchTemplates(signal)">Повторить</v-btn>
      </template>
    </v-alert>

    <SmartGrid
      class="mt-4"
      grid-id="templates-list"
      :columns="columns"
      :rows="pageRows"
      :pagination="pagination"
      :total="total"
      :sort="sort"
      :loading="store.loading"
      :row-menu="[{ action: 'edit', label: 'Редактировать', icon: 'mdi-pencil' }]"
      @update:sort="onSortChange"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>
