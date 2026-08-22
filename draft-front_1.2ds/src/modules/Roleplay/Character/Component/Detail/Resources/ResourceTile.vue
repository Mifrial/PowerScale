<script setup lang="ts">
import { ref } from 'vue';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { ResourceLimitOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceLimitOverview';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  resource: ResourceOverview;
}>();

const open = ref(false);

// Тайл показывает значение/лимит (размерные числа) и шкалу. Состав лимита
// (база + бонусы и штрафы) раскрывается по клику.
function signed(delta: number): string {
  if (delta > 0) return `+${delta}`;

  return String(delta);
}

function bonusRowKey(bonus: ResourceLimitOverview, index: number): string {
  return `${bonus.source}_${index}`;
}

function limitLabel(): string {
  return `${props.resource.currentLabel} / ${props.resource.maxLabel}`;
}
</script>

<template>
  <v-menu v-model="open" location="bottom">
    <template #activator="{ props: menuProps }">
      <v-sheet v-bind="menuProps" class="pa-2 rounded border cursor-pointer">
        <div class="d-flex align-center justify-space-between ga-2 mb-1">
          <span class="text-body-2 text-medium-emphasis text-truncate">{{ resource.name }}</span>
          <span class="text-body-2 font-weight-medium">{{ limitLabel() }}</span>
        </div>
        <v-progress-linear
          :model-value="resource.current.base"
          :max="resource.max.base"
          height="6"
          rounded
          color="primary"
        />
      </v-sheet>
    </template>

    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ resource.name }}</v-card-title>
      <v-card-text class="pt-0">
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Значение</span>
          <span class="font-weight-medium">{{ resource.currentLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Лимит</span>
          <span class="font-weight-medium">{{ resource.maxLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">База лимита</span>
          <span class="font-weight-medium">{{ resource.baseLabel }}</span>
        </div>
        <template v-if="resource.bonuses.length">
          <div class="text-caption text-medium-emphasis mt-1 mb-1">Бонусы и штрафы лимита</div>
          <div
            v-for="(bonus, index) in resource.bonuses"
            :key="bonusRowKey(bonus, index)"
            class="d-flex align-center flex-wrap ga-2 py-1"
          >
            <span class="font-weight-medium">{{ signed(bonus.delta) }}</span>
            <span class="text-medium-emphasis">|</span>
            <RuleLink v-if="bonus.sourceRuleId" :rule-id="bonus.sourceRuleId" class="text-body-2">
              {{ bonus.source }}
            </RuleLink>
            <span v-else class="text-body-2 text-medium-emphasis">{{ bonus.source }}</span>
          </div>
        </template>
        <div v-else class="text-medium-emphasis">Без бонусов и штрафов</div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
