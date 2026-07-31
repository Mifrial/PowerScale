<template>
  <div>
    <SimpleRuleEditor
      v-model:name="localName"
      v-model:code="localCode"
      v-model:description="localDescription"
      v-model:mechanicId="localMechanicId"
      v-model:tagIds="localTagIds"
      :mechanic-options="mechanicOptions"
      :tag-options="tagOptions"
    />
    <slot name="spec" />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import SimpleRuleEditor from './SimpleRuleEditor.vue'

const props = defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
}>()

const localName = ref(props.name)
const localCode = ref(props.code)
const localDescription = ref(props.description)
const localMechanicId = ref(props.mechanicId)
const localTagIds = ref(props.tagIds)

watch(() => props.name, (v) => { localName.value = v })
watch(() => props.code, (v) => { localCode.value = v })
watch(() => props.description, (v) => { localDescription.value = v })
watch(() => props.mechanicId, (v) => { localMechanicId.value = v })
watch(() => props.tagIds, (v) => { localTagIds.value = v })

watch(localName, (v) => emit('update:name', v))
watch(localCode, (v) => emit('update:code', v))
watch(localDescription, (v) => emit('update:description', v))
watch(localMechanicId, (v) => emit('update:mechanicId', v))
watch(localTagIds, (v) => emit('update:tagIds', v))
</script>
