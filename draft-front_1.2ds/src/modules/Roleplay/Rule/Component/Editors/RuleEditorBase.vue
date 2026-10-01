<script setup lang="ts">
import SimpleRuleEditor from '@/modules/Roleplay/Rule/Component/Editors/SimpleRuleEditor.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  name: string;
  code: string;
  description: string;
  keywordIds: number[];
  keywordOptions: { title: string; value: number }[];
  rules?: Rule[];
  /** Код неизменяем после создания — поле блокируется при редактировании. */
  codeDisabled?: boolean;
}>();

const emit = defineEmits<{
  'update:name': [value: string];
  'update:code': [value: string];
  'update:description': [value: string];
  'update:keywordIds': [value: number[]];
}>();

const { inner: localName } = useVModelSync({
  modelValue: () => props.name,
  onCommit: (value) => emit('update:name', value),
  clone: false,
});
const { inner: localCode } = useVModelSync({
  modelValue: () => props.code,
  onCommit: (value) => emit('update:code', value),
  clone: false,
});
const { inner: localDescription } = useVModelSync({
  modelValue: () => props.description,
  onCommit: (value) => emit('update:description', value),
  clone: false,
});
const { inner: localTagIds } = useVModelSync({
  modelValue: () => props.keywordIds,
  onCommit: (value) => emit('update:keywordIds', value),
  clone: false,
});
</script>

<template>
  <div>
    <SimpleRuleEditor
      v-model:name="localName"
      v-model:code="localCode"
      v-model:description="localDescription"
      v-model:keywordIds="localTagIds"
      :keyword-options="keywordOptions"
      :rules="rules"
      :code-disabled="codeDisabled"
    />
    <slot name="spec" />
  </div>
</template>
