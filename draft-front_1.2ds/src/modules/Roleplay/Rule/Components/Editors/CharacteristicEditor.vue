<template>
  <RuleEditorBase
    :name="name"
    @update:name="(v) => emit('update:name', v)"
    :code="code"
    @update:code="(v) => emit('update:code', v)"
    :description="description"
    @update:description="(v) => emit('update:description', v)"
    :mechanic-id="mechanicId"
    @update:mechanic-id="(v) => emit('update:mechanicId', v)"
    :tag-ids="tagIds"
    @update:tag-ids="(v) => emit('update:tagIds', v)"
    :mechanic-options="mechanicOptions"
    :tag-options="tagOptions"
  >
    <template #spec>
      <v-card class="mt-4">
        <v-card-title>Характеристика</v-card-title>
        <v-card-text>
          <div class="text-body-2 text-medium-emphasis mt-2">
            Характеристика всегда размерная с диапазоном 3-5
          </div>

          <v-switch
            v-model="hasFormula"
            label="Производная характеристика"
            color="primary"
            hide-details
            class="mt-2"
          />
          <div v-if="hasFormula" class="mt-2">
            <v-select
              v-model="formulaType"
              :items="formulaTypeOptions"
              item-title="label"
              item-value="value"
              label="Формула"
              outlined
              dense
            />
            <div class="d-flex gap-2 mt-2">
              <v-autocomplete
                v-model="formulaChar1"
                :items="availableCharacteristics"
                item-title="name"
                item-value="code"
                label="Первая характеристика"
                outlined
                dense
                clearable
                class="flex-grow-1"
              />
              <v-autocomplete
                v-model="formulaChar2"
                :items="availableCharacteristics"
                item-title="name"
                item-value="code"
                label="Вторая характеристика"
                outlined
                dense
                clearable
                class="flex-grow-1"
              />
            </div>
          </div>
        </v-card-text>
      </v-card>
    </template>
  </RuleEditorBase>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'
import RuleEditorBase from './RuleEditorBase.vue'

const props = defineProps<{
  name: string
  code: string
  description: string
  mechanicId: number | null
  tagIds: number[]
  spec: any
  mechanicOptions: { title: string; value: number }[]
  tagOptions: { title: string; value: number }[]
  spaceId: number
}>()

const emit = defineEmits<{
  'update:name': [value: string]
  'update:code': [value: string]
  'update:description': [value: string]
  'update:mechanicId': [value: number | null]
  'update:tagIds': [value: number[]]
  'update:spec': [value: any]
}>()

const revisionStore = useSpaceRevisionStore()

const allRules = computed(() => revisionStore.effectiveRules)

const hasFormula = ref(false)
const formulaType = ref<'min' | 'max'>('min')
const formulaChar1 = ref<string | null>(null)
const formulaChar2 = ref<string | null>(null)

const formulaTypeOptions = [
  { label: 'Меньшее из', value: 'min' },
  { label: 'Большее из', value: 'max' }
]

const availableCharacteristics = computed(() => {
  return allRules.value.filter((rule: Rule) => 
    rule.type === 'characteristic' && 
    rule.spaceId === props.spaceId &&
    !rule.spec?.formula
  )
})

const updateFormula = () => {
  if (hasFormula.value && formulaChar1.value && formulaChar2.value) {
    const func = formulaType.value === 'min' ? 'min' : 'max'
    emit('update:spec', { type: 'characteristic', formula: `${func}(${formulaChar1.value}, ${formulaChar2.value})` })
  } else {
    emit('update:spec', { type: 'characteristic', formula: null })
  }
}

watch([hasFormula, formulaType, formulaChar1, formulaChar2], updateFormula)

onMounted(() => {
  if (props.spec) {
    const formula = props.spec.formula || null
    if (formula) {
      hasFormula.value = true
      const match = formula.match(/(min|max)\(([^,]+),\s*([^)]+)\)/)
      if (match) {
        formulaType.value = match[1] as 'min' | 'max'
        formulaChar1.value = match[2].trim()
        formulaChar2.value = match[3].trim()
      }
    }
  }
  emit('update:spec', { type: 'characteristic', formula: hasFormula.value ? innerFormula : null })
})

const innerFormula = computed(() => {
  if (hasFormula.value && formulaChar1.value && formulaChar2.value) {
    const func = formulaType.value === 'min' ? 'min' : 'max'
    return `${func}(${formulaChar1.value}, ${formulaChar2.value})`
  }
  return null
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
