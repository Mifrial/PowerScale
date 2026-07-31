<template>
  <v-card variant="outlined" class="pa-3">
    <v-card-title class="text-subtitle-2">Ограничения характеристик</v-card-title>
    <div
      v-for="(limit, index) in localLimits"
      :key="index"
      class="d-flex gap-2 mb-2"
    >
      <v-autocomplete
        v-model="limit.characteristic_code"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        class="flex-grow-1"
      />
      <FormulaInput
        v-model="limit.limit"
        :characteristics="characteristics"
        class="flex-grow-1"
      />
      <v-btn
        icon
        size="small"
        color="error"
        @click="removeLimit(index)"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn
      variant="text"
      color="primary"
      @click="addLimit"
    >
      <v-icon start>mdi-plus</v-icon>
      Добавить ограничение
    </v-btn>
  </v-card>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import FormulaInput from './FormulaInput.vue'
import type { Formula } from './FormulaInput.vue'

interface CharacteristicLimit {
  characteristic_code: string
  limit: Formula
}

const props = defineProps<{
  modelValue: CharacteristicLimit[]
  characteristics: { code: string; name: string }[]
  defaultCharacteristicCode?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: CharacteristicLimit[]]
}>()

const localLimits = ref<CharacteristicLimit[]>([...props.modelValue])

function addLimit() {
  localLimits.value.push({
    characteristic_code: props.defaultCharacteristicCode ?? '',
    limit: { type: 'characteristic', characteristic_code: props.defaultCharacteristicCode ?? '', modifier: 0 }
  })
}

function removeLimit(index: number) {
  localLimits.value.splice(index, 1)
}

watch(localLimits, (v) => {
  emit('update:modelValue', [...v])
}, { deep: true })

watch(() => props.modelValue, (v) => {
  if (JSON.stringify(v) !== JSON.stringify(localLimits.value)) {
    localLimits.value = [...v]
  }
}, { deep: true })
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
