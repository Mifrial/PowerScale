<template>
  <div class="d-flex ga-1 align-start">
    <v-select
      :model-value="currentType"
      @update:model-value="updateType"
      :items="formulaTypes"
      item-title="label"
      item-value="value"
      label="Тип"
      density="compact"
      hide-details
      style="min-width: 110px;"
    />

    <v-text-field
      v-if="currentType === 'fixed'"
      :model-value="fixedModel?.value ?? 0"
      @update:model-value="updateValue"
      label="Значение"
      type="number"
      density="compact"
      hide-details
      style="flex: 1 1 auto;"
    />

    <template v-if="currentType === 'characteristic'">
      <v-autocomplete
        :model-value="characteristicModel?.characteristic_code"
        @update:model-value="updateCharacteristicCode"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        style="flex: 1 1 auto;"
      />
      <v-text-field
        :model-value="characteristicModel?.modifier ?? 0"
        @update:model-value="updateModifier"
        label="Модификатор"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px;"
      />
    </template>

    <template v-if="currentType === 'ability_level'">
      <v-autocomplete
        :model-value="abilityLevelModel?.ability_code"
        @update:model-value="updateAbilityCode"
        :items="abilities"
        item-title="name"
        item-value="code"
        label="Способность"
        density="compact"
        hide-details
        style="flex: 1 1 auto;"
      />
      <v-text-field
        :model-value="abilityLevelModel?.multiplier ?? 1"
        @update:model-value="updateAbilityMultiplier"
        label="Множитель"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px;"
      />
      <v-text-field
        :model-value="abilityLevelModel?.offset ?? 0"
        @update:model-value="updateAbilityOffset"
        label="Сдвиг"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px;"
      />
    </template>

    <template v-if="currentType === 'dimensional'">
      <v-text-field
        :model-value="dimensionalModel?.base ?? 3"
        @update:model-value="updateDimensionalBase"
        label="База"
        type="number"
        density="compact"
        hide-details
        style="flex: 1 1 auto;"
      />
      <v-text-field
        :model-value="dimensionalModel?.size ?? 0"
        @update:model-value="updateDimensionalSize"
        label="Размер"
        type="number"
        density="compact"
        hide-details
        style="flex: 1 1 auto;"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula'
export type { Formula }

const props = defineProps<{
  modelValue: Formula | null
  characteristics: { code: string; name: string }[]
  abilities?: { code: string; name: string }[]
  modes?: Formula['type'][]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Formula | null]
}>()

const currentType = computed<Formula['type']>(() => {
  const t = props.modelValue?.type ?? 'fixed'
  if (!props.modes?.length || props.modes.includes(t)) return t
  return 'fixed'
})

const fixedModel = computed(() =>
  props.modelValue?.type === 'fixed' ? props.modelValue : null
)

const characteristicModel = computed(() =>
  props.modelValue?.type === 'characteristic' ? props.modelValue : null
)

const abilityLevelModel = computed(() =>
  props.modelValue?.type === 'ability_level' ? props.modelValue : null
)

const dimensionalModel = computed(() =>
  props.modelValue?.type === 'dimensional' ? props.modelValue : null
)

const formulaTypes = computed(() => {
  const all = [
    { label: 'Число', value: 'fixed' },
    { label: 'От характеристики', value: 'characteristic' },
    ...(props.abilities?.length
      ? [{ label: 'Уровень способности', value: 'ability_level' }]
      : []),
    { label: 'Размерное число', value: 'dimensional' },
  ]
  const modes = props.modes ?? []
  if (!modes.length) return all
  return all.filter(t => modes.includes(t.value as Formula['type']))
})

function updateType(type: string) {
  if (type === 'fixed') {
    emit('update:modelValue', { type: 'fixed', value: 0 })
  } else if (type === 'characteristic') {
    emit('update:modelValue', { type: 'characteristic', characteristic_code: '', modifier: 0 })
  } else if (type === 'ability_level') {
    emit('update:modelValue', { type: 'ability_level', ability_code: '', multiplier: 1, offset: 0 })
  } else {
    emit('update:modelValue', { type: 'dimensional', base: 3, size: 0 })
  }
}

function updateValue(val: string) {
  emit('update:modelValue', { type: 'fixed', value: Number(val) || 0 })
}

function updateCharacteristicCode(characteristic_code: string | null) {
  const current = props.modelValue
  if (current?.type === 'characteristic') {
    emit('update:modelValue', {
      type: 'characteristic',
      characteristic_code: characteristic_code ?? '',
      modifier: current.modifier
    })
  }
}

function updateModifier(val: string) {
  const current = props.modelValue
  if (current?.type === 'characteristic') {
    emit('update:modelValue', {
      type: 'characteristic',
      characteristic_code: current.characteristic_code,
      modifier: Number(val) || 0
    })
  }
}

function updateAbilityCode(ability_code: string | null) {
  const current = props.modelValue
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: ability_code ?? '',
      multiplier: current.multiplier,
      offset: current.offset
    })
  }
}

function updateAbilityMultiplier(val: string) {
  const current = props.modelValue
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: current.ability_code,
      multiplier: Number(val) || 1,
      offset: current.offset
    })
  }
}

function updateAbilityOffset(val: string) {
  const current = props.modelValue
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: current.ability_code,
      multiplier: current.multiplier,
      offset: Number(val) || 0
    })
  }
}

function updateDimensionalBase(val: string) {
  const current = props.modelValue
  if (current?.type === 'dimensional') {
    emit('update:modelValue', {
      type: 'dimensional',
      base: Number(val) || 3,
      size: current.size
    })
  }
}

function updateDimensionalSize(val: string) {
  const current = props.modelValue
  if (current?.type === 'dimensional') {
    emit('update:modelValue', {
      type: 'dimensional',
      base: current.base,
      size: Number(val) || 0
    })
  }
}
</script>

<style scoped>
.gap-1 {
  gap: 4px;
}
</style>
