<template>
  <v-container>
    <div class="d-flex align-center mb-4">
      <h1 class="text-h5">Пространства</h1>
      <v-spacer />
      <v-btn color="primary" prepend-icon="mdi-plus" @click="router.push('/spaces/new')">
        Создать
      </v-btn>
    </div>

    <FilterBar
      :fields="filterFields"
      :model-value="appliedFilters"
      settings-key="spaces"
      @update:model-value="onFilterChange"
    />

    <v-row class="mt-4">
      <v-col v-for="space in filteredSpaces" :key="space.id" cols="12" sm="6" md="4">
        <v-card :to="`/space/${space.code}`" class="space-card">
          <v-card-title class="d-flex align-center space-card-title">
            <span class="space-card-name">{{ space.name }}</span>
            <v-spacer />
            <v-chip
              :color="space.active ? 'success' : 'grey'"
              variant="tonal"
              size="x-small"
              class="space-card-chip"
            >
              {{ space.active ? 'Активен' : 'Неактивен' }}
            </v-chip>
          </v-card-title>
          <v-card-subtitle>
            Версия {{ space.revision }}
          </v-card-subtitle>
          <v-card-text>
            <div class="text-body-2">{{ space.description }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <div v-if="filteredSpaces.length === 0 && !loading" class="text-center text-medium-emphasis pa-8">
      Пространства не найдены
    </div>
  </v-container>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useSpaceStore } from '../Store/spaces'
import { useAbortable } from '@/modules/Core/Engine/Composables/useAbortable'
import FilterBar from '@/modules/Core/UI/Component/FilterBar.vue'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'
import { extractFilterValue, extractStringFilter } from '@/modules/Core/UI/Utils/filterExtract'

const router = useRouter()
const store = useSpaceStore()
const { filteredSpaces, loading } = storeToRefs(store)
const { signal } = useAbortable()

const appliedFilters = ref<Record<string, FilterValue>>({})

onMounted(() => store.fetchSpaces(signal.value))

const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'active', label: 'Активность', type: 'active', options: [{ label: 'Активен', value: true }, { label: 'Неактивен', value: false }] },
]

function onFilterChange(filters: Record<string, FilterValue>) {
  appliedFilters.value = filters
  store.quickFilter = extractFilterValue(filters.q)
  store.filterName = extractStringFilter(filters.name)
  store.filterActive = extractFilterValue(filters.active)
}
</script>

<style scoped>
.space-card {
  height: 100%;
  transition: transform 0.2s, box-shadow 0.2s;
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
