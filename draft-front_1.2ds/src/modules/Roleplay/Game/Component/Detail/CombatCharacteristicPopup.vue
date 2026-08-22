<script setup lang="ts">
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { OverviewModifier } from '@/modules/Roleplay/Character/Dto/Overview/OverviewModifier';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { weaponMasteryEntries } from '@/modules/Roleplay/Character/Utils/weaponProficiency';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = withDefaults(
  defineProps<{
    characteristic: CharacteristicOverview;
    rules: Rule[];
    senses?: CharacterSenseValue[];
    proficiencyLevels?: Map<string, number>;
    rollable?: boolean;
    /** Бросок характеристики из попапа (показывается кубик в заголовке). */
    onRoll?: (characteristic: CharacteristicOverview) => void;
  }>(),
  {
    senses: () => [],
    proficiencyLevels: () => new Map<string, number>(),
    rollable: false,
    onRoll: undefined,
  },
);

const byId = computed(() => new Map(props.rules.map((rule) => [rule.id, rule])));

const code = computed(() => props.rules.find((rule) => rule.id === props.characteristic.ruleId)?.code ?? '');

/** Чувства относятся к Внимательности: показываем блок у неё самой или у её производных. */
const showsSenses = computed(() => {
  const codes = [
    code.value,
    ...(props.characteristic.derived?.bases ?? []).map(
      (base) => props.rules.find((rule) => rule.id === base.ruleId)?.code ?? '',
    ),
  ];

  return codes.includes('attention');
});

/** Мастерство оружий освоенных семей — только для статов мастерства (ближний/дальний бой). */
const weaponMastery = computed(() => {
  if (code.value !== 'melee-combat' && code.value !== 'ranged-combat') return [];

  return weaponMasteryEntries(code.value, { value: props.characteristic.value }, props.proficiencyLevels, props.rules);
});

const senses = computed(() =>
  props.senses.map((sense) => ({
    ruleId: sense.ruleId,
    name: byId.value.get(sense.ruleId)?.name ?? sense.ruleId,
    value: sense.value,
  })),
);

function label(value: { base: number; size: number }): string {
  return new DimensionalNumber(value).toString();
}

/** Подпись потолка модификатора-ограничения (от экипированного предмета). */
function limitLabel(limit: { base: number; size: number }): string {
  return new DimensionalNumber(limit).toString();
}

function modifierLabel(modifier: OverviewModifier): string {
  return byId.value.get(modifier.sourceRuleId ?? '')?.name ?? modifier.source ?? 'источник';
}

function signed(delta: number): string {
  if (delta > 0) return `+${delta}`;

  return String(delta);
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 460px">
    <v-card-title class="text-body-1 d-flex align-center ga-2">
      <span class="text-truncate">{{ characteristic.name }}</span>
      <button
        v-if="rollable && onRoll"
        type="button"
        class="combat-card-characteristic__roll ml-auto"
        :title="`Бросок «${characteristic.name}»`"
        @click="onRoll(characteristic)"
      >
        <v-icon size="small">mdi-dice-d6-outline</v-icon>
      </button>
    </v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Значение</span>
        <span class="font-weight-medium">{{ characteristic.valueLabel }}</span>
      </div>
      <div v-if="!characteristic.derived" class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">База</span>
        <span class="font-weight-medium">{{ characteristic.baseLabel }}</span>
      </div>

      <template v-if="(characteristic.modifiers ?? []).length > 0">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Модификаторы</div>
        <div
          v-for="(modifier, index) in characteristic.modifiers"
          :key="`${modifier.sourceRuleId}_${modifier.target}_${index}`"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span v-if="modifier.limit != null" class="font-weight-medium">
            ограничено до {{ limitLabel(modifier.limit)
            }}<template v-if="modifier.limitFormula"> ({{ modifier.limitFormula }})</template>
          </span>
          <span v-else class="font-weight-medium">{{ signed(modifier.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="modifier.sourceRuleId" :rule-id="modifier.sourceRuleId" class="text-body-2">
            {{ modifierLabel(modifier) }}
          </RuleLink>
          <span v-else class="text-body-2 text-medium-emphasis">{{ modifierLabel(modifier) }}</span>
        </div>
      </template>

      <template v-if="(characteristic.conditionalModifiers ?? []).length > 0">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Условно</div>
        <div
          v-for="(modifier, index) in characteristic.conditionalModifiers"
          :key="`${modifier.sourceRuleId}_${modifier.target}_${index}`"
          class="d-flex align-center flex-wrap ga-2 py-1"
        >
          <span class="font-weight-medium">{{ signed(modifier.delta) }}</span>
          <span class="text-medium-emphasis">|</span>
          <RuleLink v-if="modifier.sourceRuleId" :rule-id="modifier.sourceRuleId" class="text-body-2">
            {{ modifierLabel(modifier) }}
          </RuleLink>
          <span v-else class="text-body-2 text-medium-emphasis">{{ modifierLabel(modifier) }}</span>
          <span v-if="modifier.scope" class="text-caption text-medium-emphasis">условно: {{ modifier.scope }}</span>
        </div>
      </template>

      <template v-if="characteristic.derived && characteristic.derived.bases.length">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">{{ characteristic.derived.label ?? 'Производная' }}:</div>
        <v-menu
          v-for="base in characteristic.derived.bases"
          :key="base.ruleId"
          location="right top"
          open-on-hover
          :close-on-content-click="false"
          :z-index="2300"
        >
          <template #activator="{ props: menuProps }">
            <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 py-1 text-body-2 base-row">
              <span class="text-medium-emphasis">{{ base.name }}</span>
              <span class="font-weight-medium">{{ base.valueLabel }}</span>
            </div>
          </template>
          <CombatCharacteristicPopup
            :characteristic="base"
            :rules="rules"
            :senses="senses"
            :proficiency-levels="proficiencyLevels"
            :rollable="rollable"
            :on-roll="onRoll"
          />
        </v-menu>
      </template>

      <template v-if="showsSenses">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Чувства</div>
        <div
          v-for="sense in senses"
          :key="sense.ruleId"
          class="d-flex align-center justify-space-between ga-3 py-1 text-body-2"
        >
          <span class="text-medium-emphasis">{{ sense.name }}</span>
          <span class="font-weight-medium">{{ signed(sense.value) }}</span>
        </div>
      </template>

      <template v-if="weaponMastery.length">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Оружие</div>
        <v-menu
          v-for="weapon in weaponMastery"
          :key="weapon.weaponName"
          location="right top"
          open-on-hover
          :close-on-content-click="false"
          :z-index="2300"
        >
          <template #activator="{ props: menuProps }">
            <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 py-1 text-body-2 base-row">
              <span class="text-medium-emphasis">{{ weapon.weaponName }}</span>
              <span class="font-weight-medium">{{ weapon.valueLabel }}</span>
            </div>
          </template>
          <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 220px; max-width: 360px">
            <v-card-title class="text-body-1">Мастерство боя {{ weapon.weaponName }}</v-card-title>
            <v-card-text class="pt-0">
              <div class="d-flex align-center justify-space-between py-1 text-body-2">
                <span class="text-medium-emphasis">Значение</span>
                <span class="font-weight-medium">{{ weapon.valueLabel }}</span>
              </div>
              <div class="d-flex align-center justify-space-between py-1 text-body-2">
                <span class="text-medium-emphasis">База ({{ characteristic.name }})</span>
                <span class="font-weight-medium">{{ label(characteristic.value) }}</span>
              </div>
              <div class="d-flex align-center flex-wrap ga-2 py-1">
                <span class="font-weight-medium">{{ signed(weapon.bonus) }}</span>
                <span class="text-medium-emphasis">|</span>
                <span class="text-body-2 text-medium-emphasis">от владения оружием</span>
              </div>
            </v-card-text>
          </v-card>
        </v-menu>
      </template>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.base-row {
  cursor: pointer;
  border-radius: 6px;
  padding: 1px 4px;
  margin: 0 -4px;
}

.base-row:hover {
  background: rgba(var(--v-theme-primary), 0.08);
}

.combat-card-characteristic__roll {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  padding: 0;
  border: none;
  background: transparent;
  cursor: pointer;
  flex-shrink: 0;
  color: rgb(var(--v-theme-on-surface));
}
.combat-card-characteristic__roll:hover {
  color: rgb(var(--v-theme-primary));
}
</style>
