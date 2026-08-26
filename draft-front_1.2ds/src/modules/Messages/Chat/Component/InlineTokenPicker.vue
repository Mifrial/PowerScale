<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { getTokenSources } from '@/modules/Messages/Chat/init';
import type { ITokenOption } from '@/modules/Messages/Chat/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';

/**
 * Планировщик «Вставить ссылку»: поиск по источникам токенов и вставка `[[type:value]]`
 * в текст (v-textarea) на позицию курсора. Общий для чата и форм с инлайн-ссылками
 * (летопись вставляет персонажей/НПС тем же механизмом).
 */
const props = withDefaults(
  defineProps<{
    /** Источники токенов; пусто — глобальные (getTokenSources). */
    sources: ITokenSource[];
    modelValue: string;
    /** Ссылка на v-textarea, в которую вставляется токен (для позиции курсора). */
    targetRef: { $el: HTMLElement } | null;
    disabled?: boolean;
  }>(),
  {
    sources: () => [],
    disabled: false,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: string];
}>();

const sources = computed(() => (props.sources.length > 0 ? props.sources : getTokenSources()));

const pickerOpen = ref(false);
const activeSourceIndex = ref(0);
const pickerQuery = ref('');
const pickerResults = ref<ITokenOption[]>([]);
const pickerLoading = ref(false);
const pickerError = ref('');

const activeSource = computed(() => sources.value[activeSourceIndex.value] ?? null);

async function openPicker() {
  if (!sources.value.length) return;
  pickerOpen.value = true;
  activeSourceIndex.value = 0;
  pickerQuery.value = '';
  pickerError.value = '';
  await runSearch();
}

async function runSearch() {
  const source = activeSource.value;
  if (!source) return;
  pickerLoading.value = true;
  pickerError.value = '';
  try {
    pickerResults.value = await source.search(pickerQuery.value);
  } catch (e) {
    pickerResults.value = [];
    pickerError.value = e instanceof Error ? e.message : 'Не удалось загрузить список';
  } finally {
    pickerLoading.value = false;
  }
}

function selectSource(index: number) {
  activeSourceIndex.value = index;
  pickerQuery.value = '';
  void runSearch();
}

function getTextareaElement(): HTMLTextAreaElement | null {
  const component = props.targetRef;
  if (!component) return null;
  const root = component.$el as HTMLElement;
  if (root instanceof HTMLTextAreaElement) return root;

  return root.querySelector('textarea') ?? null;
}

function insertToken(option: ITokenOption) {
  const source = activeSource.value;
  if (!source) return;
  const token = `[[${source.type}:${option.value}]]`;
  const el = getTextareaElement();
  if (el) {
    const start = el.selectionStart ?? props.modelValue.length;
    const end = el.selectionEnd ?? start;
    emit('update:modelValue', props.modelValue.slice(0, start) + token + props.modelValue.slice(end));
    nextTick(() => {
      el.focus();
      el.selectionStart = el.selectionEnd = start + token.length;
    });
  } else {
    emit('update:modelValue', props.modelValue + token);
  }
  pickerOpen.value = false;
}
</script>

<template>
  <v-menu v-model="pickerOpen" :close-on-content-click="false" location="top end">
    <template #activator="{ props: menuProps }">
      <v-btn
        v-bind="menuProps"
        icon
        variant="text"
        size="x-small"
        aria-label="Вставить ссылку"
        :disabled="disabled"
        @click="openPicker"
      >
        <v-icon>mdi-link-variant</v-icon>
      </v-btn>
    </template>

    <v-card v-if="activeSource" min-width="280" max-width="340" elevation="8" border>
      <v-card-text class="pa-2">
        <div v-if="sources.length > 1" class="d-flex ga-1 mb-2">
          <v-chip
            v-for="(src, si) in sources"
            :key="src.type"
            size="x-small"
            variant="tonal"
            :color="si === activeSourceIndex ? 'primary' : undefined"
            @click="selectSource(si)"
          >
            {{ src.label }}
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
          <v-btn variant="tonal" color="primary" size="small" @click="runSearch"> Попробовать снова </v-btn>
        </div>
        <v-list v-else-if="!pickerLoading && pickerResults.length" dense class="mt-1" max-height="240">
          <v-list-item
            v-for="opt in pickerResults"
            :key="opt.value"
            density="compact"
            :prepend-icon="activeSource.icon"
            :title="opt.label"
            @click="insertToken(opt)"
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
