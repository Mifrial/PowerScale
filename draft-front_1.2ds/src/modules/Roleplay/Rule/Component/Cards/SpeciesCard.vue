<template>
  <div v-if="spec">
    <v-card v-if="parentRule" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-body-2">
          Родитель: <strong>{{ parentRule.name }}</strong>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.abilities?.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Способности (наследуются расами)</div>
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
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule'
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec'

const props = defineProps<{
  rule: Rule
  rules: Rule[]
}>()

const spec = computed<SpeciesSpec | null>(() => (props.rule.spec as SpeciesSpec) ?? null)

const rulesByCode = computed(() => {
  const map = new Map<string, Rule>()
  for (const r of props.rules) map.set(r.code, r)
  return map
})

const parentRule = computed(() => {
  const code = spec.value?.parent_race_code
  return code ? rulesByCode.value.get(code) ?? null : null
})

function abilityName(code: string): string {
  return rulesByCode.value.get(code)?.name ?? code
}
</script>
