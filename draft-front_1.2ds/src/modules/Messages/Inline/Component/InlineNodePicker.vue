<script setup lang="ts">
import { computed, ref } from 'vue';
import { getTokenSources } from '@/modules/Messages/Inline/init';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';

/** Вставка inline-сущности в rich-text: поиск по источникам и emit выбранного чипа. */
const props = withDefaults(
  defineProps<{
    sources?: ITokenSource[];
    disabled?: boolean;
  }>(),
  {
    sources: () => [],
    disabled: false,
  },
);

const emit = defineEmits<{
  insert: [entity: { type: string; value: string; label: string }];
}>();

const sources = computed(() => (props.sources.length > 0 ? props.sources : getTokenSources()));
const pickerOpen = ref(false);
const activeSourceIndex = ref(0);
const pickerQuery = ref('');
const pickerResults = ref<ITokenOption[]>([]);
const pickerLoading = ref(false);
const pickerError = ref('');

const activeSource = computed(() => sources.value[activeSourceIndex.value] ?? null);

async function openPicker(): Promise<void> {
  if (!sources.value.length) return;
  pickerOpen.value = true;
  activeSourceIndex.value = 0;
  pickerQuery.value = '';
  pickerError.value = '';
  await runSearch();
}

async function runSearch(): Promise<void> {
  const source = activeSource.value;
  if (!source) return;
  pickerLoading.value = true;
  pickerError.value = '';
  try {
    pickerResults.value = await source.search(pickerQuery.value);
  } catch (caught) {
    pickerResults.value = [];
    pickerError.value = caught instanceof Error ? caught.message : 'Не удалось загрузить список';
  } finally {
    pickerLoading.value = false;
  }
}

function selectSource(index: number): void {
  activeSourceIndex.value = index;
  pickerQuery.value = '';
  void runSearch();
}

function insert(option: ITokenOption): void {
  const source = activeSource.value;
  if (!source) return;
  emit('insert', { type: source.type, value: option.value, label: option.label });
  pickerOpen.value = false;
}
</script>

<template>
  <v-menu v-model="pickerOpen" :close-on-content-click="false" location="bottom end">
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        size="small"
        prepend-icon="mdi-link-variant"
        variant="text"
        :disabled="disabled"
        @click="openPicker"
      >
        Вставить ссылку
      </v-btn>
    </template>

    <v-card v-if="activeSource" min-width="280" max-width="340" elevation="8" border>
      <v-card-text class="pa-2">
        <div v-if="sources.length > 1" class="d-flex ga-1 mb-2">
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
        <v-text-field
          v-model="pickerQuery"
          density="compact"
          variant="outlined"
          hide-details
          :label="`Поиск: ${activeSource.label}`"
          @update:model-value="runSearch"
        />
        <div v-if="pickerError" class="text-caption text-error pa-2 text-center">
          <div class="mb-2">{{ pickerError }}</div>
          <v-btn variant="tonal" color="primary" size="small" @click="runSearch">Попробовать снова</v-btn>
        </div>
        <v-list v-else-if="!pickerLoading && pickerResults.length" density="compact" max-height="240" class="mt-1">
          <v-list-item
            v-for="option in pickerResults"
            :key="option.value"
            density="compact"
            :prepend-icon="activeSource.icon"
            :title="option.label"
            @click="insert(option)"
          />
        </v-list>
        <div v-else-if="!pickerLoading" class="text-caption text-medium-emphasis pa-2 text-center">
          Ничего не найдено
        </div>
        <div v-else class="d-flex justify-center pa-3">
          <v-progress-circular indeterminate width="2" size="22" color="primary" />
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
