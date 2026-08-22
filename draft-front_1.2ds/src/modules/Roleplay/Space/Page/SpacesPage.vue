<script setup lang="ts">
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useSpaceStore } from '@/modules/Roleplay/Space/Store/spaces';
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable';
import { useFilteredRows } from '@/modules/Core/UI/Composables/useFilteredRows';
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue';
import { filterFields } from '@/modules/Roleplay/Space/Constant/spacesGridManifest';

const router = useRouter();
const store = useSpaceStore();
const { loading } = storeToRefs(store);
const { signal } = useAbortable();

const { appliedFilters, filteredRows, onFilterChange } = useFilteredRows({
  getItems: () => store.spaces,
  fields: filterFields,
  searchFields: ['name', 'description'],
});

onMounted(() => store.fetchSpaces(signal.value));
</script>

<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Пространства</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/spaces/new')"> Создать </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="spaces"
      @update:model-value="onFilterChange"
    />

    <v-alert v-if="store.error" type="error" class="mt-4">
      {{ store.error }}
      <template #append>
        <v-btn size="small" variant="tonal" @click="store.fetchSpaces(signal)">Повторить</v-btn>
      </template>
    </v-alert>

    <v-row class="mt-4">
      <v-col v-for="space in filteredRows" :key="space.id" cols="12" sm="6" md="4">
        <v-card :to="`/space/${space.code}`" class="space-card">
          <v-card-title class="d-flex align-center space-card-title">
            <span class="space-card-name">{{ space.name }}</span>
            <v-spacer />
            <v-chip :color="space.active ? 'success' : 'grey'" variant="tonal" size="x-small" class="space-card-chip">
              {{ space.active ? 'Активен' : 'Неактивен' }}
            </v-chip>
          </v-card-title>
          <v-card-subtitle> Версия {{ space.revision }} </v-card-subtitle>
          <v-card-text>
            <div class="text-body-2">{{ space.description }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="filteredRows.length === 0 && !loading" class="text-center text-medium-emphasis pa-8">
      Пространства не найдены
    </div>
  </v-container>
</template>

<style scoped>
.space-card {
  height: 100%;
  transition:
    transform 0.2s,
    box-shadow 0.2s;
  cursor: pointer;
}
.space-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--v-theme-scrim), var(--v-shadow-sm-opacity));
}
.space-card-title {
  min-width: 0;
}
.space-card-name {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.space-card-chip {
  flex-shrink: 0;
  margin-left: 8px;
}
</style>
