<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { formatStateEffectsService } from '@/modules/Roleplay/Rule/Service/Instance/formatStateEffectsService';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed<StateSpec | null>(() => (props.rule.spec as StateSpec) ?? null);

const rulesByCode = computed(() => {
  const map = new Map<string, Rule>();
  for (const r of props.rules) map.set(r.code, r);

  return map;
});

const valueTypeLabels: Record<StateSpec['value_type'], string> = {
  flag: 'Флаг (есть/нет)',
  number: 'Целое число',
  dimensional: 'Размерное число',
};

const aggregationLabels: Record<StateSpec['aggregation'], string> = {
  sum: 'повторы суммируются',
  max: 'берётся наибольшее',
  independent: 'повторы действуют отдельно',
};

function nameByCode(code: string): string {
  return rulesByCode.value.get(code)?.name ?? code;
}
</script>

<template>
  <div v-if="spec">
    <v-card variant="tonal" class="mb-3">
      <v-card-text>
        <div class="d-flex align-center">
          <v-icon v-if="spec.icon_code" :icon="spec.icon_code" class="mr-2" color="primary" />
          <div class="text-body-2">
            Хранение значения: <strong>{{ valueTypeLabels[spec.value_type] }}</strong>
          </div>
        </div>
        <div class="text-body-2 mt-1">
          Объединение повторов: <strong>{{ aggregationLabels[spec.aggregation] }}</strong>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="spec.effects?.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Эффекты</div>
        <div v-for="(effect, index) in spec.effects" :key="index" class="text-body-2 py-1">
          <template v-if="effect.type === 'characteristic_modify'">
            Модификатор: <strong>{{ nameByCode(effect.characteristic_code) }}</strong> {{ effect.amount >= 0 ? '+' : ''
            }}{{ effect.amount }}
            <span v-if="effect.per_unit"> за каждую единицу состояния</span>
          </template>
          <template v-else>
            {{ formatStateEffectsService.stateEffectLabel(effect, nameByCode) }}
          </template>
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>
