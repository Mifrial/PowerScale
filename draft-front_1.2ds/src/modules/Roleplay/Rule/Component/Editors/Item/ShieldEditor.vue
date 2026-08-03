<script setup lang="ts">
import { ref, watch } from 'vue'
import type { ShieldBlock } from '@/modules/Roleplay/Rule/Dto/Item/ShieldBlock'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import BlockProfileEditor from '@/modules/Roleplay/Rule/Component/BlockProfileEditor.vue'

const props = defineProps<{
  shield: ShieldBlock
  damageTypes: { code: string; name: string }[]
  sources: { code: string; name: string }[]
}>()

const emit = defineEmits<{
  'update:shield': [value: ShieldBlock]
}>()

const inner = ref<ShieldBlock>(structuredClone(props.shield))

watch(() => props.shield, (value) => {
  if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
    inner.value = structuredClone(value)
  }
}, { deep: true })

watch(inner, (value) => {
  emit('update:shield', structuredClone(value))
}, { deep: true })
</script>

<template>
  <div>
    <DimensionalNumberInput
      v-model="inner.min_strength"
      label="Минимальная сила"
      mode="characteristic"
    />
    <BlockProfileEditor
      v-model="inner.block"
      :damage-types="damageTypes"
      :sources="sources"
      :show-toggle="false"
    />
  </div>
</template>
