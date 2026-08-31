<script setup lang="ts">
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  sense: CharacterSenseValue;
  rule?: Rule;
  rules: Rule[];
}>();

const byCode = computed(() => new Map(props.rules.map((rule) => [rule.code, rule])));
const statusLabels: Record<CharacterSenseValue['status'], string> = {
  precise: 'точное',
  imprecise: 'неточное',
  vague: 'смутное',
  absent: 'отсутствует',
};

const name = computed(() => props.rule?.name ?? props.sense.ruleCode);
const radius = computed(() => new DimensionalNumber(props.sense.radius).toString());

function signed(value: number): string {
  if (value > 0) return `+${value}`;

  return String(value);
}

function modifierLabel(sourceRuleCode: string | null, sourceLabel: string | null): string {
  if (sourceRuleCode) return byCode.value.get(sourceRuleCode)?.name ?? sourceLabel ?? sourceRuleCode;

  return sourceLabel ?? 'источник';
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
    <v-card-title class="text-body-1">{{ name }}</v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Значение</span>
        <span class="font-weight-medium">{{ signed(sense.value) }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Статус</span>
        <span class="font-weight-medium">{{ statusLabels[sense.status] }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Дальность</span>
        <span class="font-weight-medium">{{ radius }}</span>
      </div>

      <template v-if="rule?.description">
        <v-divider class="my-2" />
        <div class="text-body-2 text-medium-emphasis">{{ rule.description }}</div>
      </template>

      <template v-if="sense.modifiers.length">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Модификаторы</div>
        <div
          v-for="(modifier, index) in sense.modifiers"
          :key="`${modifier.sourceRuleCode}_${modifier.target}_${index}`"
          class="d-flex align-center flex-wrap ga-2 py-1 text-body-2"
        >
          <span class="font-weight-medium">{{ signed(modifier.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="modifier.sourceRuleCode" :rule-code="modifier.sourceRuleCode">
            {{ modifierLabel(modifier.sourceRuleCode, modifier.sourceLabel) }}
          </RuleLink>
          <span v-else class="text-medium-emphasis">{{
            modifierLabel(modifier.sourceRuleCode, modifier.sourceLabel)
          }}</span>
        </div>
      </template>
    </v-card-text>
  </v-card>
</template>
