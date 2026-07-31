<template>
  <div v-if="spec">
    <v-card v-if="spec.cost_os != null" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-body-2">
          Стоимость: <strong>{{ costLabel }}</strong>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="parentRule" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-body-2">
          Родитель: <strong>{{ parentRule.name }}</strong>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.characteristics?.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Характеристики</div>
        <div v-for="c in spec.characteristics" :key="c.characteristic_code" class="text-body-2">
          <strong>{{ characteristicName(c.characteristic_code) }}</strong>:
          <template v-if="c.mode === 'purchased'">
            минимум {{ formatDimensional(c.base) }}<template v-if="c.purchase?.length"> · {{ purchaseLabel(c.purchase) }}</template>
          </template>
          <template v-else>
            {{ formatDimensional(c.base) }}
          </template>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.abilities?.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Способности</div>
        <v-chip
          v-for="(ref, index) in spec.abilities"
          :key="index"
          size="small"
          :color="ref.automatic ? 'primary' : undefined"
          variant="tonal"
          class="mr-2 mb-2"
        >
          {{ abilityName(ref.ability_code) }}
          <v-chip v-if="!ref.automatic" size="x-small" variant="text" label>
            доступная
          </v-chip>
        </v-chip>
      </v-card-text>
    </v-card>

    <v-card v-if="inheritedAbilities.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Наследуемые способности (от предков)</div>
        <v-chip
          v-for="(ref, index) in inheritedAbilities"
          :key="index"
          size="small"
          variant="tonal"
          class="mr-2 mb-2"
        >
          {{ abilityName(ref.ability_code) }} · от «{{ ref.fromName }}»
        </v-chip>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Rule } from '@/modules/Roleplay/Rule/Interface/types'
import type { RaceSpec, RacePurchaseLevel, InheritedAbilityRef } from '@/modules/Roleplay/Rule/Interface/raceTypes'
import { collectInheritedAbilities } from '@/modules/Roleplay/Rule/Interface/raceTypes'

const props = defineProps<{
  rule: Rule
  rules: Rule[]
}>()

const spec = computed<RaceSpec | null>(() => (props.rule.spec as RaceSpec) ?? null)

const rulesByCode = computed(() => {
  const map = new Map<string, Rule>()
  for (const r of props.rules) map.set(r.code, r)
  return map
})

const parentRule = computed(() => {
  const code = spec.value?.parent_race_code
  return code ? rulesByCode.value.get(code) ?? null : null
})

const inheritedAbilities = computed<InheritedAbilityRef[]>(() => {
  const s = spec.value
  if (!s?.parent_race_code) return []
  return collectInheritedAbilities(s.parent_race_code, rulesByCode.value)
})

const costLabel = computed(() => {
  const cost = spec.value?.cost_os
  if (cost == null) return '—'
  if (cost < 0) return `${Math.abs(cost)} ОС (даёт)`
  return `${cost} ОС`
})

function characteristicName(code: string): string {
  return rulesByCode.value.get(code)?.name ?? code
}

function abilityName(code: string): string {
  return rulesByCode.value.get(code)?.name ?? code
}

function formatDimensional(v: { base: number; size: number }): string {
  return `${v.base}${v.size ? `×${v.size}` : ''}`
}

function purchaseLabel(purchase: RacePurchaseLevel[]): string {
  return [...purchase]
    .sort((a, b) => a.cost - b.cost)
    .map(l => `за ${l.cost} ОС → ${formatDimensional(l.value)}`)
    .join(' · ')
}
</script>
