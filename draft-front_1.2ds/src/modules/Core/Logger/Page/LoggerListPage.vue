<script setup lang="ts">
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import LogContextDialog from '@/modules/Core/Logger/Component/LogContextDialog.vue';
import { columns } from '@/modules/Core/Logger/Constant/Grid/logs/columns';
import { filterFields } from '@/modules/Core/Logger/Constant/Grid/logs/filterFields';
import { useLoggerList } from '@/modules/Core/Logger/Composables/useLoggerList';

const {
  rows,
  total,
  pagination,
  sort,
  filters,
  loading,
  error,
  reload,
  onPaginationChange,
  onFilterChange,
  onRowAction,
  detailsOpen,
  selected,
  closeDetails,
} = useLoggerList();
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">Журнал</h1>

    <FilterBar :fields="filterFields" :model-value="filters" settings-key="logs" @update:model-value="onFilterChange" />

    <v-alert v-if="error" type="error" class="mt-4">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="reload">Повторить</v-btn>
      </template>
    </v-alert>

    <p v-else-if="!loading && total === 0" class="text-medium-emphasis mt-4">Нет записей</p>

    <SmartGrid
      class="mt-4"
      grid-id="logs-list"
      :columns="columns"
      :rows="rows"
      :pagination="pagination"
      :total="total"
      :sort="sort"
      :loading="loading"
      :row-menu="[{ action: 'details', label: 'Детали', icon: 'mdi-text-box-outline' }]"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />

    <LogContextDialog :model-value="detailsOpen" :entry="selected" @update:model-value="closeDetails" />
  </v-container>
</template>
