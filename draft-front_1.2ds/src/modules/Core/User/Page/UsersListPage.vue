<script setup lang="ts">
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Core/User/Constant/Grid/users/columns';
import { filterFields } from '@/modules/Core/User/Constant/Grid/users/filterFields';
import { useUsersList } from '@/modules/Core/User/Composables/useUsersList';

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
} = useUsersList();
</script>

<template>
  <v-container>
    <h1 class="text-h5 mb-4">Пользователи</h1>

    <FilterBar
      :fields="filterFields"
      :model-value="filters"
      settings-key="users"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="error" type="error" class="mt-4">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="reload">Повторить</v-btn>
      </template>
    </v-alert>

    <p v-else-if="!loading && total === 0" class="text-medium-emphasis mt-4">Нет пользователей</p>

    <SmartGrid
      class="mt-4"
      grid-id="users-list"
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
