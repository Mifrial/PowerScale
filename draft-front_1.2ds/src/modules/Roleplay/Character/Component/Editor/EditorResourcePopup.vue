<script setup lang="ts">
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { EditorResourceView } from '@/modules/Roleplay/Character/Dto/Editor/EditorResourceView';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

defineProps<{
  resource: EditorResourceView;
}>();

function label(value: { base: number; size: number }): string {
  return new DimensionalNumber(value).toString();
}

function signed(delta: number): string {
  return delta > 0 ? `+${delta}` : String(delta);
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
    <v-card-title class="text-body-1">{{ resource.name }}</v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Текущее значение</span>
        <span class="font-weight-medium">{{ label(resource.current) }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Лимит</span>
        <span class="font-weight-medium">{{ label(resource.max) }}</span>
      </div>
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">База лимита</span>
        <span class="font-weight-medium">{{ label(resource.base) }}</span>
      </div>

      <v-divider class="my-2" />
      <div class="text-caption text-medium-emphasis mb-1">Модификаторы лимита</div>
      <div v-if="resource.bonuses.length">
        <div
          v-for="(bonus, index) in resource.bonuses"
          :key="`${bonus.sourceRuleId ?? bonus.source}_${index}`"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span class="font-weight-medium">{{ signed(bonus.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="bonus.sourceRuleId" :rule-id="bonus.sourceRuleId" class="text-body-2">
            {{ bonus.source }}
          </RuleLink>
          <span v-else class="text-body-2 text-medium-emphasis">{{ bonus.source }}</span>
        </div>
      </div>
      <div v-else class="text-medium-emphasis">Без модификаторов</div>
    </v-card-text>
  </v-card>
</template>
