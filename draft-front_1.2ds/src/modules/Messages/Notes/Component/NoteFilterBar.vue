<script setup lang="ts">
import { computed, ref } from 'vue';
import type { NoteFilter } from '@/modules/Messages/Notes/Dto/NoteFilter';
import type { NoteHost } from '@/modules/Messages/Notes/Dto/NoteHost';
import type { NoteState } from '@/modules/Messages/Notes/Enum/NoteState';
import { getUserApi } from '@/modules/Core/User/init';

const props = withDefaults(
  defineProps<{
    modelValue: NoteFilter;
    hosts: NoteHost[];
    bypass?: boolean;
  }>(),
  {
    bypass: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: NoteFilter];
}>();

const authorSearch = ref('');
const authorItems = ref<{ title: string; value: number }[]>([]);
const authorLoading = ref(false);

const stateItems = [
  { title: 'Активные', value: 'active' },
  { title: 'Архив', value: 'archived' },
  { title: 'Все', value: 'all' },
];

const hostTypeItems = computed(() => {
  const types = new Map<string, string>();
  for (const host of props.hosts) types.set(host.type, host.label);

  return [...types.entries()].map(([value, title]) => ({ value, title }));
});

const authorId = computed(() => props.modelValue.authorId ?? null);

function patch(values: Partial<NoteFilter>): void {
  emit('update:modelValue', { ...props.modelValue, ...values });
}

async function searchAuthors(query: string): Promise<void> {
  authorSearch.value = query;
  authorLoading.value = true;
  try {
    const page = await getUserApi().findPage({ limit: 10, offset: 0, q: query.trim() || undefined });
    authorItems.value = page.items.map((user) => ({
      value: user.id,
      title: [user.name, user.surname].filter(Boolean).join(' ') || user.login,
    }));
  } catch {
    authorItems.value = [];
  } finally {
    authorLoading.value = false;
  }
}
</script>

<template>
  <div class="note-filter-bar">
    <v-text-field
      :model-value="modelValue.q ?? ''"
      density="compact"
      hide-details
      label="Поиск"
      prepend-inner-icon="mdi-magnify"
      variant="outlined"
      clearable
      class="note-filter-bar__search"
      @update:model-value="patch({ q: ($event as string) || undefined })"
    />
    <v-select
      :model-value="modelValue.hostType ?? null"
      :items="hostTypeItems"
      density="compact"
      hide-details
      label="Тип сущности"
      variant="outlined"
      clearable
      class="note-filter-bar__select"
      @update:model-value="patch({ hostType: ($event as string | null) ?? undefined })"
    />
    <v-select
      :model-value="modelValue.state ?? 'active'"
      :items="stateItems"
      density="compact"
      hide-details
      label="Состояние"
      variant="outlined"
      class="note-filter-bar__select"
      @update:model-value="patch({ state: $event as NoteState })"
    />
    <v-autocomplete
      v-if="bypass"
      :model-value="authorId"
      :items="authorItems"
      :loading="authorLoading"
      :search="authorSearch"
      density="compact"
      hide-details
      label="Автор"
      variant="outlined"
      item-title="title"
      item-value="value"
      no-filter
      clearable
      class="note-filter-bar__select"
      @update:search="searchAuthors"
      @update:model-value="patch({ authorId: ($event as number | null) ?? undefined })"
    />
  </div>
</template>

<style scoped>
.note-filter-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  padding: 8px 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.note-filter-bar__search {
  flex: 1 1 220px;
  min-width: 180px;
}

.note-filter-bar__select {
  flex: 0 1 180px;
  min-width: 150px;
}
</style>
