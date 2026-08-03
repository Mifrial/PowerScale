<script setup lang="ts">
import SimpleRuleEditor from '@/modules/Roleplay/Rule/Component/Editors/SimpleRuleEditor.vue'
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync'

const props = defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  keywordIds: number[]
  mechanicOptions: { title: string; value: number }[]
  keywordOptions: { title: string; value: number }[]
  /** Код неизменяем после создания — поле блокируется при редактировании. */
  codeDisabled?: boolean
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:keywordIds': [value: number[]]
}>()

const { inner: localName } = useVModelSync({
  modelValue: () => props.name,
  onCommit: (value) => emit('update:name', value),
  clone: false,
})
const { inner: localCode } = useVModelSync({
  modelValue: () => props.code,
  onCommit: (value) => emit('update:code', value),
  clone: false,
})
const { inner: localDescription } = useVModelSync({
  modelValue: () => props.description,
  onCommit: (value) => emit('update:description', value),
  clone: false,
})
const { inner: localMechanicId } = useVModelSync({
  modelValue: () => props.mechanicId,
  onCommit: (value) => emit('update:mechanicId', value),
  clone: false,
})
const { inner: localTagIds } = useVModelSync({
  modelValue: () => props.keywordIds,
  onCommit: (value) => emit('update:keywordIds', value),
  clone: false,
})
</script>

<template>
  <div>
    <SimpleRuleEditor
      v-model:name="localName"
      v-model:code="localCode"
      v-model:description="localDescription"
      v-model:mechanicId="localMechanicId"
      v-model:keywordIds="localTagIds"
      :mechanic-options="mechanicOptions"
      :keyword-options="keywordOptions"
      :code-disabled="codeDisabled"
    />
    <slot name="spec" />
  </div>
</template>
