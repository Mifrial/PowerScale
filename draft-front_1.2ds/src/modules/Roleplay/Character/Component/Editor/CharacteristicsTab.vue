<script setup lang="ts">
import { computed, ref } from 'vue';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import EditorInnateMatrix from '@/modules/Roleplay/Character/Component/Editor/EditorInnateMatrix.vue';

const props = defineProps<{
  build: CharacterBuild;
  rules: Rule[];
  model: CharacterEditorModel;
  keywords: Keyword[];
  draftKey: string | null;
}>();

const draftStore = useCharacterDraftStore();
const purchaseOpen = ref(0);

const byCode = computed(() => new Map(props.rules.map((rule) => [rule.code, rule])));

interface PurchasedCharacteristic {
  code: string;
  name: string;
  base: string;
  ladder: { cost: number; value: string }[];
  currentCost: number;
}

const purchasedCharacteristics = computed<PurchasedCharacteristic[]>(() => {
  if (props.build.raceRuleId === null) return [];
  const raceRule = props.rules.find((rule) => rule.id === props.build.raceRuleId);
  const spec = raceRule?.type === 'race' ? (raceRule.spec as RaceSpec | undefined) : undefined;
  if (!spec) return [];

  return spec.characteristics
    .filter((characteristic) => characteristic.mode === 'purchased')
    .map((characteristic) => ({
      code: characteristic.characteristic_code,
      name: byCode.value.get(characteristic.characteristic_code)?.name ?? characteristic.characteristic_code,
      base: new DimensionalNumber(characteristic.base).toString(),
      ladder: (characteristic.purchase ?? []).map((level) => ({
        cost: level.cost,
        value: new DimensionalNumber(level.value).toString(),
      })),
      currentCost:
        props.build.characteristicPurchases.find(
          (purchase) => purchase.characteristicCode === characteristic.characteristic_code,
        )?.cost ?? 0,
    }));
});

const purchaseCosts = computed<number[]>(() => {
  const costs = new Set<number>();
  for (const characteristic of purchasedCharacteristics.value) {
    for (const rung of characteristic.ladder) costs.add(rung.cost);
  }

  return [...costs].sort((a, b) => a - b);
});

function rungOf(
  characteristic: PurchasedCharacteristic,
  cost: number,
): PurchasedCharacteristic['ladder'][number] | undefined {
  return characteristic.ladder.find((rung) => rung.cost === cost);
}

function rungValue(characteristic: PurchasedCharacteristic, cost: number): string {
  return rungOf(characteristic, cost)?.value ?? '—';
}

function setPurchase(code: string, cost: number): void {
  const purchases = props.build.characteristicPurchases.filter((purchase) => purchase.characteristicCode !== code);
  const next = cost > 0 ? [...purchases, { characteristicCode: code, cost }] : purchases;
  draftStore.patchBuild(props.draftKey, { characteristicPurchases: next });
}

function setInnateParameter(ruleId: string, code: string, value: number | { base: number; size: number }): void {
  const next = characterBuildService.setAbilityParameter(props.build, ruleId, code, value, props.rules);
  draftStore.patchBuild(props.draftKey, { abilities: next.abilities });
}
</script>

<template>
  <div>
    <v-expansion-panels
      v-if="purchasedCharacteristics.length"
      v-model="purchaseOpen"
      density="compact"
      class="editor-panel"
    >
      <v-expansion-panel>
        <v-expansion-panel-title>
          <span class="text-subtitle-2">Покупка характеристик (ОС)</span>
        </v-expansion-panel-title>
        <v-expansion-panel-text>
          <div class="purchase-table">
            <v-table density="compact">
              <thead>
                <tr>
                  <th>Название характеристики</th>
                  <th class="text-center">База</th>
                  <th v-for="cost in purchaseCosts" :key="cost" class="text-center">{{ cost }} ОС</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="characteristic in purchasedCharacteristics" :key="characteristic.code">
                  <td>{{ characteristic.name }}</td>
                  <td class="text-center">{{ characteristic.base }}</td>
                  <td v-for="cost in purchaseCosts" :key="cost" class="text-center pa-1">
                    <button
                      type="button"
                      class="purchase-cell"
                      :class="{ active: characteristic.currentCost === cost }"
                      :disabled="!rungOf(characteristic, cost)"
                      @click="setPurchase(characteristic.code, characteristic.currentCost === cost ? 0 : cost)"
                    >
                      {{ rungValue(characteristic, cost) }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </div>
        </v-expansion-panel-text>
      </v-expansion-panel>
    </v-expansion-panels>

    <EditorInnateMatrix
      class="mt-4"
      :model="model"
      :keywords="keywords"
      :rules="rules"
      @set-parameter="setInnateParameter"
    />
  </div>
</template>

<style scoped>
.purchase-table {
  padding: 8px 0;
}

:deep(.editor-panel > .v-expansion-panel > .v-expansion-panel-title) {
  min-height: 44px;
  padding: 6px 16px;
}

:deep(.editor-panel > .v-expansion-panel--active > .v-expansion-panel-title) {
  min-height: 44px;
}

:deep(.editor-panel > .v-expansion-panel > .v-expansion-panel-text > .v-expansion-panel-text__wrapper) {
  padding: 0;
}

.purchase-cell {
  min-width: 44px;
  padding: 3px 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  line-height: 1.4;
}

.purchase-cell:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.06);
}

.purchase-cell.active {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-on-primary));
  font-weight: 600;
}

.purchase-cell.active:hover {
  background: rgb(var(--v-theme-primary));
  border-color: rgb(var(--v-theme-primary));
  filter: brightness(1.1);
}

.purchase-cell:disabled {
  cursor: default;
  opacity: 0.4;
}
</style>
