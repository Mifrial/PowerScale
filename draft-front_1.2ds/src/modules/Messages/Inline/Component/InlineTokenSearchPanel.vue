<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { InlineTokenScope } from '@/modules/Messages/Inline/Dto/InlineTokenScope';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

/** Поиск inline-ссылки: источники, необязательная область и результаты. */
const props = defineProps<{
  sources: ITokenSource[];
}>();

const emit = defineEmits<{
  select: [option: ITokenOption, sourceType: string];
}>();

const activeSourceIndex = ref(0);
const scopes = ref<InlineTokenScope[]>([]);
const scopeValue = ref<string | null>(null);
const scopesLoading = ref(false);
const scopesError = ref('');
const query = ref('');
const results = ref<ITokenOption[]>([]);
const loading = ref(false);
const error = ref('');
let scopeRequest = 0;
let searchRequest = 0;

const activeSource = computed(() => props.sources[activeSourceIndex.value] ?? null);

watch(
  () => props.sources,
  () => void selectSource(0),
  { immediate: true },
);

async function selectSource(index: number): Promise<void> {
  const request = ++scopeRequest;
  searchRequest++;
  activeSourceIndex.value = index;
  query.value = '';
  results.value = [];
  scopeValue.value = null;
  scopes.value = [];
  scopesLoading.value = false;
  scopesError.value = '';
  error.value = '';
  loading.value = false;
  const source = activeSource.value;
  if (!source) return;
  const loadScopes = source.loadScopes;
  if (loadScopes) {
    scopesLoading.value = true;
    try {
      const loaded = await loadScopes();
      if (request !== scopeRequest) return;
      scopes.value = loaded;
      scopeValue.value = loaded[0]?.value ?? null;
    } catch (caught) {
      if (request !== scopeRequest) return;
      scopesError.value = errorText(caught, 'Не удалось загрузить список');

      return;
    } finally {
      if (request === scopeRequest) scopesLoading.value = false;
    }
  }
  if (request !== scopeRequest) return;
  await runSearch();
}

async function runSearch(): Promise<void> {
  const source = activeSource.value;
  if (!source) return;
  if (source.loadScopes && scopeValue.value === null) {
    results.value = [];

    return;
  }
  const request = ++searchRequest;
  loading.value = true;
  error.value = '';
  try {
    const found = await source.search(query.value, scopeValue.value ?? undefined);
    if (request !== searchRequest) return;
    results.value = found;
  } catch (caught) {
    if (request !== searchRequest) return;
    results.value = [];
    error.value = errorText(caught, 'Не удалось загрузить список');
  } finally {
    if (request === searchRequest) loading.value = false;
  }
}

function onQueryChange(): void {
  void runSearch();
}

function onScopeChange(): void {
  void runSearch();
}

function retry(): void {
  if (scopesError.value !== '') {
    void selectSource(activeSourceIndex.value);

    return;
  }
  void runSearch();
}

function errorText(caught: unknown, fallback: string): string {
  return caught instanceof Error ? caught.message : fallback;
}
</script>

<template>
  <v-card min-width="280" max-width="340" elevation="8" border>
    <v-card-text class="pa-2">
      <div v-if="sources.length > 1" class="d-flex ga-1 mb-2 flex-wrap">
        <v-chip
          v-for="(source, index) in sources"
          :key="source.type"
          size="x-small"
          variant="tonal"
          :color="index === activeSourceIndex ? 'primary' : undefined"
          @click="selectSource(index)"
        >
          {{ source.label }}
        </v-chip>
      </div>
      <v-select
        v-if="activeSource && activeSource.loadScopes"
        v-model="scopeValue"
        :items="scopes"
        item-title="label"
        item-value="value"
        :label="activeSource.scopeLabel ?? 'Область'"
        density="compact"
        variant="outlined"
        hide-details
        class="mb-2"
        :loading="scopesLoading"
        :disabled="scopesLoading"
        @update:model-value="onScopeChange"
      />
      <div v-if="scopesError" class="text-caption text-error pa-2 text-center">
        <div class="mb-2">{{ scopesError }}</div>
        <v-btn variant="tonal" color="primary" size="small" @click="retry">Попробовать снова</v-btn>
      </div>
      <div
        v-else-if="activeSource && activeSource.loadScopes && !scopesLoading && scopes.length === 0"
        class="text-caption text-medium-emphasis pa-2 text-center"
      >
        Нет доступных вариантов
      </div>
      <template v-else>
        <v-text-field
          v-model="query"
          density="compact"
          variant="outlined"
          hide-details
          :label="`Поиск: ${activeSource?.label ?? ''}`"
          :disabled="scopesLoading"
          @update:model-value="onQueryChange"
        />
        <div v-if="error" class="text-caption text-error pa-2 text-center">
          <div class="mb-2">{{ error }}</div>
          <v-btn variant="tonal" color="primary" size="small" @click="retry">Попробовать снова</v-btn>
        </div>
        <v-list v-else-if="!loading && results.length" density="compact" max-height="240" class="mt-1">
          <v-list-item
            v-for="option in results"
            :key="option.value"
            density="compact"
            :prepend-icon="activeSource?.icon"
            :title="option.label"
            @click="emit('select', option, activeSource?.type ?? '')"
          />
        </v-list>
        <div v-else-if="!loading" class="text-caption text-medium-emphasis pa-2 text-center">Ничего не найдено</div>
        <div v-else class="d-flex justify-center pa-3">
          <v-progress-circular indeterminate width="2" size="22" color="primary" />
        </div>
      </template>
    </v-card-text>
  </v-card>
</template>
