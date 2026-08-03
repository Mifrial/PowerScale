<script setup lang="ts">
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic'
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef'
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber'
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService'
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync'

const props = defineProps<{
  modelValue: RaceCharacteristic[]
  characteristics: CharacteristicRef[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: RaceCharacteristic[]]
}>()

const { inner } = useVModelSync<RaceCharacteristic[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
})

function specWith(list: RaceCharacteristic[]): RaceSpec {
  return { parent_race_code: null, cost_os: 0, characteristics: list, abilities: [] }
}

function addCharacteristic() {
  inner.value = raceSpecService.addCharacteristic(specWith(inner.value)).characteristics
}

function patchCharacteristic(index: number, key: 'characteristic_code' | 'mode', value: string | null) {
  inner.value = raceSpecService.patchCharacteristic(specWith(inner.value), index, key, value).characteristics
}

function patchCharacteristicBase(index: number, value: DimensionalNumberValue | null) {
  inner.value = raceSpecService.patchCharacteristicBase(specWith(inner.value), index, value).characteristics
}

function removeCharacteristic(index: number) {
  inner.value = raceSpecService.removeCharacteristic(specWith(inner.value), index).characteristics
}

function addPurchaseLevel(index: number) {
  inner.value = raceSpecService.addPurchaseLevel(specWith(inner.value), index).characteristics
}

function patchPurchaseLevel(index: number, levelIndex: number, key: 'cost' | 'value', value: number | DimensionalNumberValue | null) {
  inner.value = raceSpecService.patchPurchaseLevel(specWith(inner.value), index, levelIndex, key, value).characteristics
}

function removePurchaseLevel(index: number, levelIndex: number) {
  inner.value = raceSpecService.removePurchaseLevel(specWith(inner.value), index, levelIndex).characteristics
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      «Базовая» — фиксированное значение (дальше правит только дары черт). «Докупаемая» —
      минимум (за 0 ОС) + таблица закупки «за N ОС → значение».
    </div>
    <div
      v-for="(c, index) in inner"
      :key="index"
      class="pa-2 mb-2 rounded bg-accent"
    >
      <div class="bg-surface rounded pa-2">
        <div class="d-flex ga-2 align-center mb-1">
          <v-autocomplete
            :model-value="c.characteristic_code"
            @update:model-value="(v) => patchCharacteristic(index, 'characteristic_code', v)"
            :items="characteristics"
            item-title="name"
            item-value="code"
            label="Характеристика"
            density="compact"
            hide-details
            class="flex-grow-1"
          />
          <v-radio-group
            :model-value="c.mode"
            @update:model-value="(v) => patchCharacteristic(index, 'mode', v)"
            density="compact"
            hide-details
            inline
          >
            <v-radio label="Базовая" value="fixed" />
            <v-radio label="Докупаемая" value="purchased" />
          </v-radio-group>
          <v-btn
            icon
            size="small"
            color="error"
            variant="text"
            @click="removeCharacteristic(index)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>

        <div v-if="c.mode === 'fixed'">
          <DimensionalNumberInput
            :model-value="c.base"
            @update:model-value="(v) => patchCharacteristicBase(index, v)"
            label="Значение"
            mode="characteristic"
          />
        </div>

        <div v-else>
          <DimensionalNumberInput
            :model-value="c.base"
            @update:model-value="(v) => patchCharacteristicBase(index, v)"
            label="Минимум (за 0 ОС)"
            mode="characteristic"
          />
          <div class="text-subtitle-2 mt-2 mb-1">Закупка</div>
          <div
            v-for="(level, levelIndex) in c.purchase ?? []"
            :key="levelIndex"
            class="d-flex ga-2 align-center mb-1"
          >
            <ClampedNumberField
              :model-value="level.cost"
              @update:model-value="(v) => patchPurchaseLevel(index, levelIndex, 'cost', v)"
              label="ОС"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 90px;"
            />
            <DimensionalNumberInput
              :model-value="level.value"
              @update:model-value="(v) => patchPurchaseLevel(index, levelIndex, 'value', v)"
              label="Значение"
            />
            <v-btn
              icon
              size="small"
              color="error"
              variant="text"
              @click="removePurchaseLevel(index, levelIndex)"
            >
              <v-icon>mdi-delete</v-icon>
            </v-btn>
          </div>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="addPurchaseLevel(index)"
          >
            <v-icon start>mdi-plus</v-icon>
            Добавить уровень
          </v-btn>
        </div>
      </div>
    </div>
    <v-btn
      variant="text"
      color="primary"
      size="small"
      @click="addCharacteristic"
    >
      <v-icon start>mdi-plus</v-icon>
      Добавить характеристику
    </v-btn>
  </div>
</template>
