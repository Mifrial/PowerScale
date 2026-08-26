<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import { CHARACTERISTIC_GROUPS } from '@/modules/Roleplay/Rule/Constant/CHARACTERISTIC_GROUPS';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed<CharacteristicSpec | null>(() =>
  props.rule.type === 'characteristic' ? ((props.rule.spec as CharacteristicSpec | undefined) ?? null) : null,
);

const groupLabel = computed(() => {
  const group = spec.value?.group ?? 'primary';

  return CHARACTERISTIC_GROUPS.find((entry) => entry.value === group)?.title ?? group;
});

const automaticLabel = computed(() => {
  const automatic = spec.value?.automatic;
  if (!automatic) return null;
  if (automatic === true) return 'есть у всех, база 3 средних';
  if (typeof automatic === 'object') return `есть у всех, база ${ruleViewLabelService.dimensional(automatic.value)}`;

  return null;
});

const baseFromLabel = computed(() => {
  const from = spec.value?.base_from;
  if (!from) return null;
  const sources = from.source_codes.map((code) => ruleViewLabelService.ruleName(props.rules, code)).join(', ');

  return `из «${ruleViewLabelService.ruleName(props.rules, from.characteristic_code)}»${sources ? ` (${sources})` : ''}`;
});
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-3">
    <v-card-text>
      <div class="text-body-2">
        Группа: <strong>{{ groupLabel }}</strong>
      </div>
      <div v-if="spec.formula" class="text-body-2 mt-1">
        Формула: <strong>{{ spec.formula }}</strong>
      </div>
      <div v-if="automaticLabel" class="text-body-2 mt-1">Автоматическая: {{ automaticLabel }}</div>
      <div v-if="baseFromLabel" class="text-body-2 mt-1">База: {{ baseFromLabel }}</div>
    </v-card-text>
  </v-card>
</template>
