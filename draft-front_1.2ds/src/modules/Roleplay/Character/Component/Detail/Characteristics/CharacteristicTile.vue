<script setup lang="ts">
import { ref } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { OverviewModifier } from '@/modules/Roleplay/Character/Dto/Overview/OverviewModifier';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = withDefaults(
  defineProps<{
    characteristic: CharacteristicOverview;
    // Встраиваемый режим для композитных блоков: без собственной рамки, только имя и значение.
    embedded?: boolean;
    // Подпись под именем внутри кликабельной области (напр. «Минимальная из →»).
    caption?: string | null;
  }>(),
  { embedded: false, caption: null },
);

const open = ref(false);

// Тайл показывает итоговое значение (размерное число). Состав (значение = база + модификаторы)
// раскрывается по клику; условные модификаторы (scope) в значение не входят.
function signed(delta: number): string {
  if (delta > 0) return `+${delta}`;

  return String(delta);
}

function modifierRowKey(modifier: OverviewModifier, index: number): string {
  return `${modifier.source}_${modifier.target}_${index}`;
}

function modifierLabel(modifier: OverviewModifier): string {
  if (modifier.sourceLevel === null) return modifier.source;

  return `${modifier.source} ${modifier.sourceLevel}`;
}

function showSubtitle(): boolean {
  return props.embedded ? false : props.characteristic.subtitle !== null;
}

function label(): string {
  return props.characteristic.shortName ?? props.characteristic.name;
}
</script>

<template>
  <v-menu v-model="open" location="bottom">
    <template #activator="{ props: menuProps }">
      <div v-if="embedded" v-bind="menuProps" class="cursor-pointer">
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis text-truncate">{{ label() }}</span>
          <span class="text-body-2 font-weight-medium">{{ characteristic.valueLabel }}</span>
        </div>
        <div v-if="caption !== null" class="text-caption text-medium-emphasis text-truncate">{{ caption }}</div>
      </div>
      <v-sheet v-else v-bind="menuProps" class="pa-2 rounded border cursor-pointer" style="min-height: 32px">
        <div class="d-flex align-center justify-space-between ga-2">
          <span class="text-body-2 text-medium-emphasis text-truncate">{{ label() }}</span>
          <span class="text-body-2 font-weight-medium">{{ characteristic.valueLabel }}</span>
        </div>
        <div v-if="showSubtitle()" class="text-caption text-medium-emphasis text-truncate">
          {{ characteristic.subtitle }}
        </div>
        <div v-if="caption !== null" class="text-caption text-medium-emphasis text-truncate">{{ caption }}</div>
      </v-sheet>
    </template>

    <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ label() }}</v-card-title>
      <v-card-text class="pt-0">
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">Значение</span>
          <span class="font-weight-medium">{{ characteristic.valueLabel }}</span>
        </div>
        <div class="d-flex align-center justify-space-between py-1 text-body-2">
          <span class="text-medium-emphasis">База</span>
          <span class="font-weight-medium">{{ characteristic.baseLabel }}</span>
        </div>
        <div
          v-for="(modifier, index) in characteristic.modifiers"
          :key="modifierRowKey(modifier, index)"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span v-if="modifier.limit != null" class="font-weight-medium">
            ограничено до {{ new DimensionalNumber(modifier.limit).toString()
            }}<template v-if="modifier.limitFormula"> ({{ modifier.limitFormula }}) </template>
          </span>
          <span v-else class="font-weight-medium">{{ signed(modifier.delta) }}</span>
          <span v-if="modifier.sourceRole" class="text-medium-emphasis">{{ modifier.sourceRole }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="modifier.sourceRuleId" :rule-id="modifier.sourceRuleId" class="text-body-2">
            {{ modifierLabel(modifier) }}
          </RuleLink>
          <span v-else class="text-body-2 text-medium-emphasis">{{ modifierLabel(modifier) }}</span>
        </div>
        <div
          v-for="(modifier, index) in characteristic.conditionalModifiers"
          :key="modifierRowKey(modifier, index)"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span class="font-weight-medium">{{ signed(modifier.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <span class="text-body-2 text-medium-emphasis">{{ modifier.source }}</span>
          <span class="text-caption text-medium-emphasis">условно: {{ modifier.scope }}</span>
        </div>
        <div
          v-if="characteristic.modifiers.length === 0 && characteristic.conditionalModifiers.length === 0"
          class="text-medium-emphasis"
        >
          Без модификаторов
        </div>
      </v-card-text>
    </v-card>
  </v-menu>
</template>
