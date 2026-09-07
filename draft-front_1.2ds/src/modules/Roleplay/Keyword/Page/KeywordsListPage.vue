<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Roleplay/Keyword/Constant/Grid/keywords/columns';
import { filterFields } from '@/modules/Roleplay/Keyword/Constant/Grid/keywords/filterFields';
import { useKeywordList } from '@/modules/Roleplay/Keyword/Composables/useKeywordList';

const { currentUser } = useCurrentUser();
const canCreate = computed(() => accessService.hasAnyPermission(currentUser.value, ['keyword.create']));
const {
  loading,
  error,
  load,
  goCreate,
  onRowAction,
  sort,
  pagination,
  appliedFilters,
  pageRows,
  total,
  onSortChange,
  onPaginationChange,
  onFilterChange,
} = useKeywordList();

onMounted(load);
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Признаки</h1>
      <v-spacer />
      <v-btn v-if="canCreate" color="primary" prepend-icon="mdi-plus" @click="goCreate"> Создать </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="keywords"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="error" type="error" class="mt-4">
      {{ error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="load">Повторить</v-btn>
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
      :loading="loading"
      :row-menu="[{ action: 'view-profile', label: 'Посмотреть профиль', icon: 'mdi-account-outline' }]"
      @update:sort="onSortChange"
      @update:pagination="onPaginationChange"
      @row-action="onRowAction"
    />
  </v-container>
</template>
