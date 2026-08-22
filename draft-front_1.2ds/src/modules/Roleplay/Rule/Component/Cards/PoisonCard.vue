<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PoisonSpec } from '@/modules/Roleplay/Rule/Dto/Poison/PoisonSpec';
import { periodicityLabel, decayLabel } from '@/modules/Roleplay/Rule/Utils/State/formatStateEffects';
import DimensionalNumber from '@/modules/Core/UI/Component/Input/DimensionalNumber.vue';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed<PoisonSpec | null>(() => (props.rule.spec as PoisonSpec) ?? null);

const rulesByCode = computed(() => {
  const map = new Map<string, Rule>();
  for (const r of props.rules) map.set(r.code, r);

  return map;
});

function nameByCode(code: string): string {
  return rulesByCode.value.get(code)?.name ?? code;
}
</script>

<template>
  <div v-if="spec">
    <v-card variant="tonal" class="mb-3">
      <v-card-text>
        <div class="d-flex align-center">
          <v-icon v-if="spec.icon_code" :icon="spec.icon_code" class="mr-2" color="error" />
          <div class="text-body-2">
            Тип урона яда: <strong>{{ nameByCode(spec.damage_type_code) }}</strong>
          </div>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.default_strength != null || spec.default_periodicity" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Параметры по умолчанию</div>
        <div class="d-flex align-center ga-2">
          <span class="text-body-2">Сила:</span>
          <DimensionalNumber v-if="spec.default_strength" :value="spec.default_strength" />
          <span v-else class="text-body-2">—</span>
        </div>
        <div class="text-body-2 mt-1">
          Периодичность: <strong>{{ periodicityLabel(spec.default_periodicity) }}</strong>
        </div>
        <div v-if="spec.default_decay" class="text-body-2 mt-1">
          Затухание: <strong>{{ decayLabel(spec.default_decay, nameByCode) }}</strong>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>
