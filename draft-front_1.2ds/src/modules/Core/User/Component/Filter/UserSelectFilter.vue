<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { useUserStore } from '@/modules/Core/User/Store/users';
import { useUserSearch } from '@/modules/Core/User/Composables/useUserSearch';
import { toUserId } from '@/modules/Core/User/Service/Field/UserFieldTypeInterpreter';

const props = defineProps<{
  field: FilterField;
  modelValue?: unknown;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: number | null | undefined];
}>();

const store = useUserStore();
const { options, loading, error, labelOf, search, loadInitial, loadById } = useUserSearch();
const searchText = ref('');

const selectedId = computed(() => toUserId(props.modelValue));

const selectedLabel = computed(() => {
  const id = selectedId.value;
  if (id === null) return null;
  const known = store.users.find((user) => user.id === id);

  return known ? labelOf(known) : `#${id}`;
});

const items = computed(() => {
  const rows = options.value.map((user) => ({ title: labelOf(user), value: user.id }));
  const selected = selectedId.value;
  if (selected === null || rows.some((row) => row.value === selected)) return rows;

  return [{ title: selectedLabel.value ?? `#${selected}`, value: selected }, ...rows];
});

watch(
  selectedId,
  (id) => {
    if (id !== null) void loadById(id);
  },
  { immediate: true },
);

onMounted(() => void loadInitial());

function onSearch(text: string): void {
  searchText.value = text;
  if (selectedLabel.value !== null && text === selectedLabel.value) return;
  search(text);
}

function onSelect(value: number | null): void {
  emit('update:modelValue', value ?? null);
}
</script>

<template>
  <v-autocomplete
    :model-value="selectedId"
    :items="items"
    :label="field.label"
    :loading="loading"
    :search="searchText"
    item-title="title"
    item-value="value"
    density="compact"
    hide-details
    variant="outlined"
    clearable
    no-filter
    class="flex-grow-1"
    @click.stop
    @update:search="onSearch"
    @update:model-value="onSelect"
  >
    <template #no-data>
      <v-list-item v-if="error" :title="error" />
      <v-list-item v-else-if="loading" title="Поиск…" />
      <v-list-item v-else title="Пользователи не найдены" />
    </template>
  </v-autocomplete>
</template>
