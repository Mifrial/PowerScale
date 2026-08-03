<script setup lang="ts">
import type { ZoneId } from '@/modules/Roleplay/Rule/Dto/Ability/ZoneId'
import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost'
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue'
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync'

const props = defineProps<{
  modelValue: Partial<Record<ZoneId, AbilityCost>>
  zoneOptions: { label: string; value: string }[]
  disabled?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Partial<Record<ZoneId, AbilityCost>>]
}>()

const { inner } = useVModelSync<Partial<Record<ZoneId, AbilityCost>>>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: false,
})

function hasZone(zone: ZoneId): boolean {
  return !!inner.value[zone]
}

function zoneCost(zone: ZoneId): AbilityCost {
  return inner.value[zone] ?? { kind: 'array', levels_cost: [0] }
}

function progressionZone(zone: ZoneId) {
  const cost = inner.value[zone]
  return cost && cost.kind === 'progression' ? cost : null
}

function toggleZone(zone: ZoneId, checked: boolean) {
  const zones = { ...inner.value }
  if (checked) {
    zones[zone] = { kind: 'array', levels_cost: [0] }
  } else {
    delete zones[zone]
  }
  inner.value = zones
}

function patchZone(zone: ZoneId, key: string, value: unknown) {
  const current = inner.value[zone]
  if (!current) return
  inner.value = { ...inner.value, [zone]: { ...current, [key]: value } as AbilityCost }
}

function arrayCosts(zone: ZoneId): number[] {
  const cost = inner.value[zone]
  return cost && cost.kind === 'array' ? cost.levels_cost : []
}

function updateArrayCost(zone: ZoneId, index: number, value: number) {
  const cost = inner.value[zone]
  if (!cost || cost.kind !== 'array') return
  const levels_cost = [...cost.levels_cost]
  levels_cost[index] = Number(value) || 0
  inner.value = { ...inner.value, [zone]: { kind: 'array', levels_cost } }
}

function addArrayCost(zone: ZoneId) {
  const cost = inner.value[zone]
  if (!cost || cost.kind !== 'array') return
  inner.value = { ...inner.value, [zone]: { kind: 'array', levels_cost: [...cost.levels_cost, 0] } }
}

function removeArrayCost(zone: ZoneId) {
  const cost = inner.value[zone]
  if (!cost || cost.kind !== 'array' || cost.levels_cost.length <= 1) return
  inner.value = { ...inner.value, [zone]: { kind: 'array', levels_cost: cost.levels_cost.slice(0, -1) } }
}
</script>

<template>
  <div>
    <div class="d-flex flex-wrap ga-4">
      <v-checkbox
        v-for="zone in zoneOptions"
        :key="zone.value"
        :model-value="hasZone(zone.value)"
        @update:model-value="(v: boolean | null) => toggleZone(zone.value, !!v)"
        :label="zone.label"
        density="compact"
        hide-details
      />
    </div>

    <div
      v-for="zone in zoneOptions"
      :key="`zone-editor-${zone.value}`"
      class="mt-3"
    >
      <template v-if="hasZone(zone.value)">
        <div class="text-subtitle-2 mb-1">{{ zone.label }}</div>
        <v-radio-group
          :model-value="zoneCost(zone.value).kind"
          @update:model-value="(v) => patchZone(zone.value, 'kind', v)"
          density="compact"
          hide-details
          class="mb-1"
        >
          <v-radio label="Массив" value="array" />
          <v-radio label="Прогрессия" value="progression" />
          <v-radio label="Авто-получение" value="automatic" />
        </v-radio-group>

        <template v-if="zoneCost(zone.value).kind === 'array'">
          <div class="text-body-2 text-medium-emphasis mb-1">
            Стоимости по уровням (отрицательная = даёт очки). Длина = макс. уровень.
          </div>
          <div class="d-flex ga-2 align-center flex-wrap">
            <ClampedNumberField
              v-for="(cost, levelIndex) in arrayCosts(zone.value)"
              :key="levelIndex"
              :model-value="cost"
              @update:model-value="updateArrayCost(zone.value, levelIndex, $event)"
              :label="`Ур. ${levelIndex + 1}`"
              density="compact"
              hide-details
              style="min-width: 90px;"
            />
            <v-btn icon size="small" variant="text" @click="addArrayCost(zone.value)">
              <v-icon>mdi-plus</v-icon>
            </v-btn>
            <v-btn icon size="small" variant="text" @click="removeArrayCost(zone.value)">
              <v-icon>mdi-minus</v-icon>
            </v-btn>
          </div>
        </template>

        <template v-else-if="zoneCost(zone.value).kind === 'progression'">
          <div class="d-flex gap-2 mt-1">
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.max_level ?? 1"
              @update:model-value="patchZone(zone.value, 'max_level', $event)"
              label="Макс. уровень"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 140px;"
            />
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.base_cost ?? 0"
              @update:model-value="patchZone(zone.value, 'base_cost', $event)"
              label="Базовая стоимость"
              density="compact"
              hide-details
              style="min-width: 140px;"
            />
            <ClampedNumberField
              :model-value="progressionZone(zone.value)?.step ?? 0"
              @update:model-value="patchZone(zone.value, 'step', $event)"
              label="Шаг за уровень"
              density="compact"
              hide-details
              style="min-width: 140px;"
            />
          </div>
        </template>

        <template v-else>
          <div class="text-body-2 text-medium-emphasis">
            Способность получается автоматически при выполнении требований и не покупается.
          </div>
        </template>
      </template>
    </div>
  </div>
</template>
