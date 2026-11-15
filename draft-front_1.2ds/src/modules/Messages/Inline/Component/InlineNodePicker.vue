<script setup lang="ts">
import { computed, ref } from 'vue';
import { getTokenSources } from '@/modules/Messages/Inline/init';
import type { ITokenOption } from '@/modules/Messages/Inline/Interface/ITokenOption';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import InlineTokenSearchPanel from '@/modules/Messages/Inline/Component/InlineTokenSearchPanel.vue';

/** Вставка inline-сущности в rich-text: выбор из источников и emit чипа. */
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

const availableSources = computed(() => (props.sources.length > 0 ? props.sources : getTokenSources()));
const pickerOpen = ref(false);
const pickerKey = ref(0);

function openPicker(): void {
  if (!availableSources.value.length) return;
  pickerKey.value += 1;
  pickerOpen.value = true;
}

function insert(option: ITokenOption, sourceType: string): void {
  emit('insert', { type: sourceType, value: option.value, label: option.label });
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

    <InlineTokenSearchPanel :key="pickerKey" :sources="availableSources" @select="insert" />
  </v-menu>
</template>
