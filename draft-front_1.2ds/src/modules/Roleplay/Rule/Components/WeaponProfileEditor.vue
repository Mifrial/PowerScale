<template>
  <div>
    <div class="mb-2">
      <label class="text-caption text-medium-emphasis">Дистанция</label>
      <FormulaInput
        v-model="localProfile.distance"
        :characteristics="characteristics"
      />
    </div>

    <div v-if="localProfile.type === 'throw' || localProfile.type === 'shoot'" class="mb-2">
      <label class="text-caption text-medium-emphasis">Дальнобойность</label>
      <FormulaInput
        v-model="localProfile.range"
        :characteristics="characteristics"
      />
    </div>

    <v-card class="mt-2 pa-3" variant="outlined">
      <v-card-title class="text-subtitle-2">Урон</v-card-title>
      <div class="d-flex gap-2">
        <div style="flex: 1 1 auto;">
          <FormulaInput
            v-model="localProfile.damage.formula"
            :characteristics="characteristics"
          />
        </div>
        <v-autocomplete
          v-model="localProfile.damage.damage_type_code"
          :items="damageTypes"
          item-title="name"
          item-value="code"
          label="Тип урона"
          density="compact"
          hide-details
          clearable
          style="min-width: 150px;"
        />
      </div>
    </v-card>

    <v-card class="mt-2 pa-3" variant="outlined">
      <v-card-title class="text-subtitle-2">Пробитие</v-card-title>
      <FormulaInput
        v-model="localProfile.penetration"
        :characteristics="characteristics"
      />
    </v-card>

    <DimensionalNumberInput
      v-model="localProfile.accuracy"
      label="Точность"
      class="mt-2"
    />

    <v-btn
      color="error"
      variant="text"
      @click="emit('remove')"
      class="mt-2"
    >
      <v-icon start>mdi-delete</v-icon>
      Удалить профиль
    </v-btn>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import DimensionalNumberInput from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Components/DimensionalNumberInput.vue'
import FormulaInput from './FormulaInput.vue'
import type { Formula } from './FormulaInput.vue'

interface WeaponProfile {
  type: 'strike' | 'throw' | 'shoot'
  distance: Formula
  range: Formula | null
  damage: { formula: Formula; damage_type_code: string | null }
  penetration: Formula
  accuracy: DimensionalNumberValue
}

const props = defineProps<{
  modelValue: WeaponProfile
  damageTypes: { code: string; name: string }[]
  characteristics: { code: string; name: string }[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: WeaponProfile]
  'remove': []
}>()

const localProfile = ref<WeaponProfile>({ ...props.modelValue })

const strengthCode = computed(() => {
  const found = props.characteristics.find(c => c.name === 'Сила')
  return found ? found.code : ''
})

function isDefaultDistance(f: Formula): boolean {
  if (f.type === 'fixed' && f.value === 0) return true
  if (f.type === 'characteristic' && (!f.characteristic_code || f.characteristic_code === '') && f.modifier === 0) return true
  return false
}

watch(() => props.modelValue, (value) => {
  if (JSON.stringify(value) !== JSON.stringify(localProfile.value)) {
    localProfile.value = { ...value }
  }
}, { deep: true })

watch(() => props.modelValue.type, (newType) => {
  const profile = localProfile.value
  if (newType === 'shoot') {
    if (isDefaultDistance(profile.distance)) {
      profile.distance = { type: 'fixed', value: 20 }
    }
    if (!profile.range || isDefaultDistance(profile.range)) {
      profile.range = { type: 'fixed', value: 10 }
    }
  } else if (newType === 'throw') {
    if (isDefaultDistance(profile.distance)) {
      profile.distance = { type: 'characteristic', characteristic_code: strengthCode.value, modifier: 0 }
    }
    if (!profile.range || isDefaultDistance(profile.range)) {
      profile.range = { type: 'fixed', value: 2 }
    }
  } else {
    if (isDefaultDistance(profile.distance)) {
      profile.distance = { type: 'fixed', value: 0 }
    }
    profile.range = null
  }
})

watch(localProfile, (value) => {
  emit('update:modelValue', { ...value })
}, { deep: true })
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
