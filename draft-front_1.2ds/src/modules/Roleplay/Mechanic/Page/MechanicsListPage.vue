<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { accessService, useCurrentUser } from '@/modules/Core/User/init';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import SmartGrid from '@/modules/Core/UI/Component/Grid/SmartGrid.vue';
import { columns } from '@/modules/Roleplay/Mechanic/Constant/Grid/mechanics/columns';
import { filterFields } from '@/modules/Roleplay/Mechanic/Constant/Grid/mechanics/filterFields';
import { useMechanicList } from '@/modules/Roleplay/Mechanic/Composables/useMechanicList';

const { currentUser } = useCurrentUser();
const canCreate = computed(() => accessService.hasAnyPermission(currentUser.value, ['mechanic.create']));
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
} = useMechanicList();

onMounted(load);
</script>

<template>
  <v-container>
    <Teleport to="#editor-actions">
      <v-btn
        v-if="canCreate"
        variant="tonal"
        color="primary"
        size="small"
        prepend-icon="mdi-plus"
        @click="goCreate"
      >
        Создать
      </v-btn>
    </Teleport>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="mechanics"
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
      grid-id="mechanics-list"
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
