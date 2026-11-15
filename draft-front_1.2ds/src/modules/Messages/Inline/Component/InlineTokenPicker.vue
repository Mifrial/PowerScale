<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { getTokenSources } from '@/modules/Messages/Inline/init';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import InlineTokenSearchPanel from '@/modules/Messages/Inline/Component/InlineTokenSearchPanel.vue';

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

const availableSources = computed(() => (props.sources.length > 0 ? props.sources : getTokenSources()));
const pickerOpen = ref(false);
const pickerKey = ref(0);

function openPicker(): void {
  if (!availableSources.value.length) return;
  pickerKey.value += 1;
  pickerOpen.value = true;
}

function getTextareaElement(): HTMLTextAreaElement | null {
  const component = props.targetRef;
  if (!component) return null;
  const root = component.$el as HTMLElement;
  if (root instanceof HTMLTextAreaElement) return root;

  return root.querySelector('textarea') ?? null;
}

function insertToken(option: ITokenOption, sourceType: string): void {
  const token = `[[${sourceType}:${option.value}]]`;
  const element = getTextareaElement();
  if (element) {
    const start = element.selectionStart ?? props.modelValue.length;
    const end = element.selectionEnd ?? start;
    emit('update:modelValue', props.modelValue.slice(0, start) + token + props.modelValue.slice(end));
    nextTick(() => {
      element.focus();
      element.selectionStart = element.selectionEnd = start + token.length;
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

    <InlineTokenSearchPanel :key="pickerKey" :sources="availableSources" @select="insertToken" />
  </v-menu>
</template>
