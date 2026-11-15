<script setup lang="ts">
import { ref, watch } from 'vue';
import type { LoreReader } from '@/modules/Roleplay/Lore/Dto/LoreReader';
import { getLoreApi } from '@/modules/Roleplay/Lore/init';
import { LoreApiError } from '@/modules/Roleplay/Lore/Service/LoreApiError';

const props = defineProps<{
  modelValue: number[];
  readers: LoreReader[];
  worldCode: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: number[]];
}>();

const search = ref('');
const candidates = ref<LoreReader[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
let requestId = 0;

const selected = ref<LoreReader[]>([...props.readers]);

watch(
  () => props.readers,
  (readers) => {
    selected.value = [...readers];
  },
);

watch(search, () => {
  void loadCandidates();
});

async function loadCandidates(): Promise<void> {
  const current = ++requestId;
  loading.value = true;
  error.value = null;
  try {
    const result = await getLoreApi().findReaderCandidates({
      worldCode: props.worldCode,
      q: search.value.trim() === '' ? undefined : search.value.trim(),
      limit: 20,
    });
    if (current !== requestId) return;
    const selectedIds = new Set(props.modelValue);
    candidates.value = result.filter((reader) => !selectedIds.has(reader.id));
  } catch (exception) {
    if (current !== requestId) return;
    error.value = exception instanceof LoreApiError ? exception.message : 'Не удалось найти пользователей';
  } finally {
    if (current === requestId) loading.value = false;
  }
}

function add(reader: LoreReader): void {
  if (props.modelValue.includes(reader.id)) return;
  selected.value = [...selected.value, reader];
  emit('update:modelValue', [...props.modelValue, reader.id]);
  search.value = '';
}

function remove(readerId: number): void {
  selected.value = selected.value.filter((reader) => reader.id !== readerId);
  emit(
    'update:modelValue',
    props.modelValue.filter((id) => id !== readerId),
  );
}

function nameOf(reader: LoreReader): string {
  return `${reader.name} (${reader.login})`;
}

void loadCandidates();
</script>

<template>
  <div>
    <div class="d-flex flex-wrap ga-2 mb-2">
      <v-chip v-for="reader in selected" :key="reader.id" closable size="small" @click:close="remove(reader.id)">
        {{ nameOf(reader) }}
      </v-chip>
      <span v-if="selected.length === 0" class="text-caption text-medium-emphasis">
        Читатели не выбраны — секретную статью увидят только владелец и админы кодекса.
      </span>
    </div>
    <v-text-field
      v-model="search"
      label="Найти пользователя"
      prepend-inner-icon="mdi-account-search"
      density="compact"
      hide-details
      :loading="loading"
    />
    <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-2">{{ error }}</v-alert>
    <v-list v-if="candidates.length > 0" density="compact" class="mt-1 overflow-y-auto" max-height="220">
      <v-list-item
        v-for="candidate in candidates"
        :key="candidate.id"
        :title="candidate.name"
        :subtitle="candidate.login"
        prepend-icon="mdi-account-plus"
        @click="add(candidate)"
      />
    </v-list>
  </div>
</template>
