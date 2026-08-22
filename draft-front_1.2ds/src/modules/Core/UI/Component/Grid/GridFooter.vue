<script setup lang="ts">
import { computed } from 'vue';
import type { Pagination } from '@/modules/Core/UI/Dto/Grid/Pagination';
import { perPageOptions } from '@/modules/Core/UI/Constant/Grid/perPageOptions';

const props = defineProps<{
  pagination: Pagination | null;
  totalItems: number;
}>();

const emit = defineEmits<{
  'update:pagination': [pagination: Pagination];
}>();

const totalPages = computed(() => {
  if (!props.pagination?.perPage) return 1;

  return Math.max(1, Math.ceil(props.totalItems / props.pagination.perPage));
});

function onPageChange(v: number) {
  if (props.pagination) {
    emit('update:pagination', { ...props.pagination, page: v });
  }
}

function onPerPageChange(v: number) {
  emit('update:pagination', { page: 1, perPage: v });
}
</script>

<template>
  <div v-if="pagination" class="smart-grid-footer d-flex align-center justify-end ga-3 pa-2">
    <v-select
      :model-value="pagination.perPage"
      :items="perPageOptions"
      label="Записей"
      density="compact"
      hide-details
      variant="outlined"
      style="flex: none; width: 110px"
      @update:model-value="onPerPageChange"
    />
    <v-spacer />
    <v-pagination
      v-if="totalPages > 1"
      :model-value="pagination.page"
      :length="totalPages"
      density="compact"
      size="28"
      total-visible="5"
      @update:model-value="onPageChange"
    />
  </div>
</template>

<style scoped>
.smart-grid-footer {
  border-top: thin solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
