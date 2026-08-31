<script setup lang="ts">
import type { EditorCheckBonus } from '@/modules/Roleplay/Character/Dto/Editor/EditorCheckBonus';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  bonus: EditorCheckBonus;
  rules: Rule[];
}>();

function sourceName(sourceRuleCode: string | null, sourceLabel: string | null): string {
  return props.rules.find((rule) => rule.code === sourceRuleCode)?.name ?? sourceLabel ?? 'источник';
}

function signed(delta: number): string {
  return delta > 0 ? `+${delta}` : String(delta);
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
    <v-card-title class="text-body-1">{{ bonus.checkName }}</v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Итоговый бонус</span>
        <span class="font-weight-medium">{{ signed(bonus.delta) }}</span>
      </div>
      <v-divider class="my-2" />
      <div class="text-caption text-medium-emphasis mb-1">Модификаторы</div>
      <div
        v-for="(modifier, index) in bonus.modifiers"
        :key="`${modifier.sourceRuleCode ?? modifier.sourceLabel}_${index}`"
        class="d-flex align-center flex-wrap ga-2 py-1"
      >
        <span class="font-weight-medium">{{ signed(modifier.delta) }}</span>
        <span class="text-medium-emphasis">|</span>
        <RuleLink v-if="modifier.sourceRuleCode" :rule-code="modifier.sourceRuleCode" class="text-body-2">
          {{ sourceName(modifier.sourceRuleCode, modifier.sourceLabel) }}
        </RuleLink>
        <span v-else class="text-body-2 text-medium-emphasis">
          {{ sourceName(modifier.sourceRuleCode, modifier.sourceLabel) }}
        </span>
      </div>
    </v-card-text>
  </v-card>
</template>
