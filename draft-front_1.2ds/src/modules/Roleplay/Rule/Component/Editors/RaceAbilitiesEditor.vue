<script setup lang="ts">
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef'
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef'
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec'
import { raceSpecService } from '@/modules/Roleplay/Rule/Service/Spec/RaceSpecService'
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync'

const props = defineProps<{
  modelValue: RaceAbilityRef[]
  abilities: AbilityRef[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: RaceAbilityRef[]]
}>()

const { inner } = useVModelSync<RaceAbilityRef[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
})

function specWith(list: RaceAbilityRef[]): RaceSpec {
  return { parent_race_code: null, cost_os: 0, characteristics: [], abilities: list }
}

function addAbility() {
  inner.value = raceSpecService.addAbility(specWith(inner.value)).abilities
}

function patchAbility(index: number, key: 'ability_code' | 'automatic', value: string | boolean) {
  inner.value = raceSpecService.patchAbility(specWith(inner.value), index, key, value).abilities
}

function removeAbility(index: number) {
  inner.value = raceSpecService.removeAbility(specWith(inner.value), index).abilities
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Свои способности расы. Способности предков-видов наследуются автоматически
      (см. «Превью наследования»).
    </div>
    <div
      v-for="(ref, index) in inner"
      :key="index"
      class="d-flex ga-2 align-center mb-1"
    >
      <v-autocomplete
        :model-value="ref.ability_code"
        @update:model-value="(v) => patchAbility(index, 'ability_code', v)"
        :items="abilities"
        item-title="name"
        item-value="code"
        label="Способность"
        density="compact"
        hide-details
        class="flex-grow-1"
      />
      <v-switch
        :model-value="ref.automatic"
        @update:model-value="(v) => patchAbility(index, 'automatic', !!v)"
        label="Бесплатная"
        density="compact"
        hide-details
      />
      <v-btn
        icon
        size="small"
        color="error"
        variant="text"
        @click="removeAbility(index)"
      >
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>
    <v-btn
      variant="text"
      color="primary"
      size="small"
      @click="addAbility"
    >
      <v-icon start>mdi-plus</v-icon>
      Добавить способность
    </v-btn>
  </div>
</template>
