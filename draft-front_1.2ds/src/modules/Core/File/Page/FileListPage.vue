<script setup lang="ts">
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import FileDetailsDialog from '@/modules/Core/File/Component/FileDetailsDialog.vue';
import { columns } from '@/modules/Core/File/Constant/Grid/files/columns';
import { useFileList } from '@/modules/Core/File/Composables/useFileList';
import { useFileFilterFields } from '@/modules/Core/File/Composables/useFileFilterFields';

const { fields: filterFields } = useFileFilterFields();

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
} = useFileList();
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">Файлы</h1>

    <FilterBar
      :fields="filterFields"
      :model-value="filters"
      settings-key="files"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="error" type="error" class="mt-4">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="reload">Повторить</v-btn>
      </template>
    </v-alert>

    <p v-else-if="!loading && total === 0" class="text-medium-emphasis mt-4">Нет файлов</p>

    <SmartGrid
      class="mt-4"
      grid-id="files-list"
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

    <FileDetailsDialog :model-value="detailsOpen" :file="selected" @update:model-value="closeDetails" />
  </v-container>
</template>
