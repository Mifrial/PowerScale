<script setup lang="ts">
import { computed, ref } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DefenseArmorOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import CharacteristicTile from '@/modules/Roleplay/Character/Component/Detail/Characteristics/CharacteristicTile.vue';
import DerivedCharacteristicTile from '@/modules/Roleplay/Character/Component/Detail/Characteristics/DerivedCharacteristicTile.vue';
import CombatSection from '@/modules/Roleplay/Character/Component/Detail/Characteristics/CombatSection.vue';
import ResourceTile from '@/modules/Roleplay/Character/Component/Detail/Resources/ResourceTile.vue';
import MiscTile from '@/modules/Roleplay/Character/Component/Detail/Resources/MiscTile.vue';
import AttackTile from '@/modules/Roleplay/Character/Component/Detail/Attacks/AttackTile.vue';
import DefenseValue from '@/modules/Roleplay/Character/Component/Detail/Defense/DefenseValue.vue';
import ArmorTile from '@/modules/Roleplay/Character/Component/Detail/Defense/ArmorTile.vue';
import StateTile from '@/modules/Roleplay/Character/Component/Detail/States/StateTile.vue';

const props = defineProps<{
  version: CharacterVersion;
  rules: Rule[];
  rulesLoading: boolean;
  rulesError: string | null;
}>();

// Секции обзора: раскрыты по умолчанию, пользователь может сворачивать.
const expanded = ref<string[]>(['characteristics', 'resources', 'misc', 'states', 'defense', 'attacks']);

const overview = computed(() => characterOverviewService.build(props.version, props.rules));

const primarySimple = computed(() => overview.value.characteristics.filter((c) => c.group === 'primary' && !c.derived));
const primaryDerived = computed(() => overview.value.characteristics.filter((c) => c.group === 'primary' && c.derived));
const importantCharacteristics = computed(() => overview.value.characteristics.filter((c) => c.group === 'important'));
const secondaryCharacteristics = computed(() => overview.value.characteristics.filter((c) => c.group === 'secondary'));

function armorKey(item: DefenseArmorOverview): string {
  return item.itemRuleId;
}

function attackKey(attack: AttackOverview): string {
  return `${attack.itemRuleId}_${attack.profileType}`;
}
</script>

<template>
  <div class="overview-tab d-flex flex-column ga-4">
    <v-expansion-panels v-model="expanded" multiple class="overview-panels">
      <v-expansion-panel value="characteristics">
        <v-expansion-panel-title>Характеристики</v-expansion-panel-title>
        <v-expansion-panel-text>
          <template v-if="primarySimple.length || primaryDerived.length">
            <div class="text-subtitle-2 text-medium-emphasis mb-1">Основные</div>
            <v-row v-if="primarySimple.length" dense>
              <v-col v-for="characteristic in primarySimple" :key="characteristic.ruleId" cols="6" sm="4" md="3">
                <CharacteristicTile :characteristic="characteristic" />
              </v-col>
            </v-row>
            <v-row v-if="primaryDerived.length" dense class="mt-2">
              <v-col v-for="characteristic in primaryDerived" :key="characteristic.ruleId" cols="12" md="6">
                <DerivedCharacteristicTile :characteristic="characteristic" />
              </v-col>
            </v-row>
          </template>

          <div v-if="overview.combat" class="mt-3">
            <CombatSection :combat="overview.combat" />
          </div>

          <template v-if="importantCharacteristics.length">
            <div class="text-subtitle-2 text-medium-emphasis mt-3 mb-1">Важные</div>
            <v-row dense>
              <v-col
                v-for="characteristic in importantCharacteristics"
                :key="characteristic.ruleId"
                cols="6"
                sm="4"
                md="3"
              >
                <CharacteristicTile :characteristic="characteristic" />
              </v-col>
            </v-row>
          </template>

          <template v-if="secondaryCharacteristics.length">
            <div class="text-subtitle-2 text-medium-emphasis mt-3 mb-1">Вторичные</div>
            <v-row dense>
              <v-col
                v-for="characteristic in secondaryCharacteristics"
                :key="characteristic.ruleId"
                cols="6"
                sm="4"
                md="3"
              >
                <CharacteristicTile :characteristic="characteristic" />
              </v-col>
            </v-row>
          </template>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="resources">
        <v-expansion-panel-title>Ресурсы</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-row v-if="overview.resources.length" dense>
            <v-col v-for="resource in overview.resources" :key="resource.ruleId" cols="6">
              <ResourceTile :resource="resource" />
            </v-col>
          </v-row>
          <div v-else class="text-medium-emphasis">Ресурсов нет</div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="misc">
        <v-expansion-panel-title>Разное</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-row dense>
            <v-col v-for="item in overview.misc" :key="item.code" cols="6">
              <MiscTile :item="item" />
            </v-col>
          </v-row>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel v-if="overview.states.length" value="states">
        <v-expansion-panel-title>Состояния</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-row dense>
            <v-col v-for="state in overview.states" :key="state.id" cols="6" sm="4" md="3">
              <StateTile :state="state" />
            </v-col>
          </v-row>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="defense">
        <v-expansion-panel-title>
          <div class="d-flex align-center justify-space-between w-100 pr-2">
            <span>Защита</span>
            <DefenseValue
              v-if="overview.defense && overview.defense.constantDefense > 0"
              :tiers="overview.defense.tiers"
            />
          </div>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <template v-if="overview.defense">
            <v-row dense>
              <v-col v-for="item in overview.defense.armor" :key="armorKey(item)" cols="6">
                <ArmorTile :item="item" />
              </v-col>

              <v-col v-if="overview.defense.shield" cols="6">
                <v-sheet class="shield-tile pa-2 rounded border d-flex flex-column">
                  <div class="d-flex align-center ga-2">
                    <v-icon icon="mdi-shield-outline" color="primary" />
                    <RuleLink :rule-id="overview.defense.shield.itemRuleId" class="text-body-2 font-weight-medium">
                      {{ overview.defense.shield.itemName }} — блокирование
                    </RuleLink>
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    Защита {{ overview.defense.shield.defense }} · эффективность
                    {{ overview.defense.shield.efficiency }}
                  </div>
                </v-sheet>
              </v-col>
            </v-row>
          </template>
          <div v-else class="text-medium-emphasis">Экипированной защиты нет</div>
        </v-expansion-panel-text>
      </v-expansion-panel>

      <v-expansion-panel value="attacks">
        <v-expansion-panel-title>Атаки</v-expansion-panel-title>
        <v-expansion-panel-text>
          <v-row v-if="overview.attacks.length" dense>
            <v-col v-for="attack in overview.attacks" :key="attackKey(attack)" cols="6">
              <AttackTile :attack="attack" />
            </v-col>
          </v-row>
          <div v-else class="text-medium-emphasis">Экипированного оружия нет</div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>

<style scoped>
.shield-tile {
  height: 100%;
  justify-content: center;
}
</style>
