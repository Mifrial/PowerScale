<script setup lang="ts">
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Core/User/Constant/Grid/groups/columns';
import { filterFields } from '@/modules/Core/User/Constant/Grid/groups/filterFields';
import { useGroupsList } from '@/modules/Core/User/Composables/useGroupsList';

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
  goCreate,
  onRowAction,
} = useGroupsList();
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions">
      <v-btn variant="tonal" color="primary" size="small" prepend-icon="mdi-plus" @click="goCreate"> Создать </v-btn>
    </Teleport>

    <FilterBar
      :fields="filterFields"
      :model-value="filters"
      settings-key="groups"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="error" type="error" class="mt-4">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="reload">Повторить</v-btn>
      </template>
    </v-alert>

    <p v-else-if="!loading && total === 0" class="text-medium-emphasis mt-4">Нет групп</p>

    <SmartGrid
      class="mt-4"
      grid-id="groups-list"
      :columns="columns"
      :rows="rows"
      :pagination="pagination"
      :total="total"
      :sort="sort"
      :loading="loading"
      :row-menu="[{ action: 'view-profile', label: 'Посмотреть профиль', icon: 'mdi-account-outline' }]"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>
