<template>
  <div>
    <div v-if="showToggle" class="d-flex align-center gap-2 mt-2 mb-2">
      <label class="text-body-2 font-weight-medium" style="min-width: 120px;">Профиль блокирования</label>
      <v-switch
        v-model="active"
        hide-details
      />
    </div>

    <template v-if="active">
      <div class="d-flex gap-2 mb-2">
        <DimensionalNumberInput
          v-model="localProfile.efficiency"
          label="Эффективность"
        />
        <v-text-field
          v-model.number="localProfile.defense"
          label="Защита"
          type="number"
          density="compact"
          style="max-width: 100px;"
        />
      </div>
      <ResistanceSlotsEditor
        v-model="localProfile.resistances"
        :damage-types="damageTypes"
        :sources="sources"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import ResistanceSlotsEditor from './ResistanceSlotsEditor.vue'

interface ResistanceSlot {
  damage_type_code: string | null
  value: number
  durability: number
  source_code: string | null
}

interface BlockProfile {
  efficiency: DimensionalNumberValue
  defense: number
  resistances: ResistanceSlot[]
}

const props = defineProps<{
  modelValue: BlockProfile | null
  damageTypes: { code: string; name: string }[]
  sources: { code: string; name: string }[]
  showToggle?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: BlockProfile | null]
}>()

const active = ref(!!props.modelValue)

const defaultProfile = (): BlockProfile => ({
  efficiency: { base: 3, size: 0 },
  defense: 0,
  resistances: []
})

const localProfile = ref<BlockProfile>(props.modelValue ?? defaultProfile())

watch(() => props.modelValue, (v) => {
  if (v) {
    localProfile.value = v
  }
})

watch(active, (isActive) => {
  if (isActive) {
    const profile = defaultProfile()
    localProfile.value = profile
    emit('update:modelValue', profile)
  } else {
    emit('update:modelValue', null)
  }
})

watch(localProfile, (v) => {
  if (active.value) {
    emit('update:modelValue', { ...v })
  }
}, { deep: true })
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
