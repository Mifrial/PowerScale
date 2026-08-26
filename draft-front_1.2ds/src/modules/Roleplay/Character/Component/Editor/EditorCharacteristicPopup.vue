<script setup lang="ts">
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { CharacterSenseValue } from '@/modules/Roleplay/Character/Dto/CharacterSenseValue';
import type { EditorCharacteristic } from '@/modules/Roleplay/Character/Dto/Editor/EditorCharacteristic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { EditorStatView } from '@/modules/Roleplay/Character/Dto/Editor/EditorStatView';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  stat: EditorStatView;
  rules: Rule[];
  senses: CharacterSenseValue[];
  /** Уровни «Владения оружием» по семьям (для статов мастерства) — блок мастерства оружий. */
  proficiencyLevels?: Map<string, number>;
}>();

const byId = computed(() => new Map(props.rules.map((rule) => [rule.id, rule])));

/** Чувства относятся к Внимательности: показываем блок у неё самой или у её производных. */
const showsSenses = computed(() => {
  const codes = [props.stat.characteristic.code, ...props.stat.bases.map((base) => base.code)];

  return codes.includes('attention');
});

/** Мастерство оружий освоенных семей — только для статов мастерства (ближний/дальний бой). */
const weaponMastery = computed(() => {
  const code = props.stat.characteristic.code;
  if (code !== 'melee-combat' && code !== 'ranged-combat') return [];
  if (!props.proficiencyLevels) return [];

  return weaponProficiencyService.weaponMasteryEntries(
    code,
    props.stat.characteristic,
    props.proficiencyLevels,
    props.rules,
  );
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

function modifierLabel(modifier: CharacteristicModifier): string {
  const name = byId.value.get(modifier.sourceRuleId ?? '')?.name ?? modifier.sourceLabel ?? 'источник';

  return name;
}

function signed(delta: number): string {
  if (delta > 0) return `+${delta}`;

  return String(delta);
}

/** View базовой характеристики (Внимательность/Реакция) для вложенного попапа. */
function baseStatView(base: EditorCharacteristic): EditorStatView {
  return {
    characteristic: base,
    rule: byId.value.get(base.ruleId),
    derived: false,
    bases: [],
  };
}
</script>

<template>
  <v-card class="rounded border" elevation="3" style="width: max-content; min-width: 280px; max-width: 460px">
    <v-card-title class="text-body-1">{{ stat.characteristic.name }}</v-card-title>
    <v-card-text class="pt-0">
      <div class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">Значение</span>
        <span class="font-weight-medium">{{ label(stat.characteristic.value) }}</span>
      </div>
      <div v-if="!stat.derived" class="d-flex align-center justify-space-between py-1 text-body-2">
        <span class="text-medium-emphasis">База</span>
        <span class="font-weight-medium">{{ label(stat.characteristic.base) }}</span>
      </div>

      <div
        v-for="(modifier, index) in stat.characteristic.modifiers"
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
        <span v-if="modifier.scope" class="text-caption text-medium-emphasis">условно: {{ modifier.scope }}</span>
      </div>

      <template v-if="stat.derived && stat.bases.length">
        <v-divider class="my-2" />
        <div class="text-caption text-medium-emphasis mb-1">Минимальная из:</div>
        <v-menu
          v-for="base in stat.bases"
          :key="base.code"
          location="right top"
          open-on-hover
          :close-on-content-click="false"
        >
          <template #activator="{ props: menuProps }">
            <div v-bind="menuProps" class="d-flex align-center justify-space-between ga-3 py-1 text-body-2 base-row">
              <span class="text-medium-emphasis">{{ base.name }}</span>
              <span class="font-weight-medium">{{ label(base.value) }}</span>
            </div>
          </template>
          <EditorCharacteristicPopup :stat="baseStatView(base)" :rules="rules" :senses="props.senses" />
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
                <span class="text-medium-emphasis">База ({{ stat.characteristic.name }})</span>
                <span class="font-weight-medium">{{ label(stat.characteristic.value) }}</span>
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
</style>
