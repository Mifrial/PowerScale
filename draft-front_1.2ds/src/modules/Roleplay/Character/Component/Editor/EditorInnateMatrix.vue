<script setup lang="ts">
import { computed } from 'vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacterEditorModel } from '@/modules/Roleplay/Character/Dto/Editor/CharacterEditorModel';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityParameter } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityParameter';
import { innateCharacteristicKindService } from '@/modules/Roleplay/Character/Service/Instance/innateCharacteristicKindService';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const props = defineProps<{
  model: CharacterEditorModel;
  keywords: Keyword[];
  rules: Rule[];
}>();

const emit = defineEmits<{
  'set-parameter': [ruleCode: string, code: string, value: number | { base: number; size: number }];
}>();

/** Имя характеристики по коду — из правил ревизии (в модели характеристика может отсутствовать,
 *  если дар её ещё не задал: «Врождённая Магия X» до взятия показывает Магию, а не код magic). */
const byCode = computed(() => new Map(props.rules.map((rule) => [rule.code, rule])));

/** Черты характеристик (признак «Характеристика»), видимые на вкладке. */
const innateAbilities = computed(() =>
  props.model.abilities.filter((ability) => ability.characteristic && ability.visible),
);

const innateKinds = computed(() =>
  innateCharacteristicKindService.split(innateAbilities.value, props.keywords, props.rules),
);

/** Модификаторы: ±X к характеристике. */
const modifierAbilities = computed(() => innateKinds.value.modifiers);

/** Дары: значение характеристики (потолок), напр. «Врождённая Магия X». */
const giftAbilities = computed(() => innateKinds.value.gifts);

interface InnateRow {
  ability: EditorAbility;
  parameter: EditorAbilityParameter;
  /** Код целевой характеристики (модификатор) или null (дар). */
  characteristicCode: string | null;
  /** Название строки: характеристика или способность. */
  name: string;
  subtitle: string | null;
  /** Итог: база+модификатор для модификатора, текущее значение для дара. */
  total: string;
  current: number;
}

function giftName(ability: EditorAbility): string {
  return ability.name.replace(/\s*[XХ]\s*$/, '').trim();
}

function giftSubtitle(parameter: EditorAbilityParameter): string | null {
  const label = parameter.label.trim();
  if (!label || label === 'X' || label === 'Х') return null;

  return label;
}

function characteristicName(code: string): string {
  return byCode.value.get(code)?.name ?? code;
}

function paramOf(ability: EditorAbility): EditorAbilityParameter | null {
  return ability.parameters[0] ?? null;
}

function paramValue(param: EditorAbilityParameter): number {
  return param.value.base;
}

function sameStep(a: { base: number; size: number }, b: { base: number; size: number }): boolean {
  return a.base === b.base && a.size === b.size;
}

function isChosen(param: EditorAbilityParameter, value: { base: number; size: number }): boolean {
  return sameStep(value, param.value);
}

function stepDisabled(param: EditorAbilityParameter, value: { base: number; size: number }): boolean {
  const rank = new DimensionalNumber(value).toNumber();

  return rank < new DimensionalNumber(param.min).toNumber() || rank > new DimensionalNumber(param.max).toNumber();
}

function valueLabel(value: { base: number; size: number }): string {
  return new DimensionalNumber(value).toString();
}

function totalOf(ability: EditorAbility, characteristicCode: string | null): string {
  const param = paramOf(ability);
  if (!param) return '—';
  if (characteristicCode === null) {
    // Дар: значение = текущий выбор (потолок).
    return paramValue(param) === 0 ? '—' : valueLabel(param.value);
  }
  const characteristic = props.model.characteristics.find((c) => c.code === characteristicCode);
  if (!characteristic) return '—';

  return new DimensionalNumber(characteristic.value).toString();
}

function rowsOf(list: EditorAbility[], kind: 'modifier' | 'gift'): InnateRow[] {
  return list
    .map((ability) => {
      const parameter = paramOf(ability);
      if (!parameter) return null;

      return {
        ability,
        parameter,
        characteristicCode: ability.characteristicCode,
        name:
          kind === 'gift'
            ? giftName(ability)
            : ability.characteristicCode
              ? characteristicName(ability.characteristicCode)
              : ability.name,
        subtitle: kind === 'gift' ? giftSubtitle(parameter) : null,
        total: totalOf(ability, ability.characteristicCode),
        current: paramValue(parameter),
      };
    })
    .filter((row): row is InnateRow => row !== null);
}

const modifierRows = computed(() => rowsOf(modifierAbilities.value, 'modifier'));
const giftRows = computed(() => rowsOf(giftAbilities.value, 'gift'));

/** Общий набор значений X (ступеней) по всем модификаторам — единая сетка столбцов. */
const modifierSteps = computed(() => {
  const byKey = new Map<string, { value: { base: number; size: number }; cost: number }>();
  for (const ability of modifierAbilities.value) {
    const param = paramOf(ability);
    if (!param) continue;
    for (const step of param.steps) byKey.set(valueLabel(step.value), { value: step.value, cost: step.cost });
  }

  return [...byKey.values()].sort(
    (a, b) => new DimensionalNumber(a.value).toNumber() - new DimensionalNumber(b.value).toNumber(),
  );
});

const giftSteps = computed(() => {
  const byKey = new Map<string, { value: { base: number; size: number }; cost: number }>();
  for (const ability of giftAbilities.value) {
    const param = paramOf(ability);
    if (!param) continue;
    for (const step of param.steps) byKey.set(valueLabel(step.value), { value: step.value, cost: step.cost });
  }

  return [...byKey.values()].sort(
    (a, b) => new DimensionalNumber(a.value).toNumber() - new DimensionalNumber(b.value).toNumber(),
  );
});

/** Дополнительные параметры покупки (второй и далее): у дара с пулом их несколько. */
interface ExtraParameterRow {
  ability: EditorAbility;
  parameter: EditorAbilityParameter;
  label: string;
}

const extraParameterRows = computed<ExtraParameterRow[]>(() =>
  innateAbilities.value.flatMap((ability) =>
    ability.parameters.slice(1).map((parameter) => ({
      ability,
      parameter,
      label: `${giftName(ability)} · ${parameter.label}`,
    })),
  ),
);

/** Индекс текущей ступени параметра; -1 — значение не выбрано. */
function stepIndex(param: EditorAbilityParameter): number {
  return param.steps.findIndex((step) => sameStep(step.value, param.value));
}

function canStepUpExtra(param: EditorAbilityParameter): boolean {
  if (param.steps.length) {
    const index = stepIndex(param);

    return index >= 0 ? index < param.steps.length - 1 : param.value.base < param.max.base;
  }

  return param.value.base < param.max.base;
}

function canStepDownExtra(param: EditorAbilityParameter): boolean {
  if (param.steps.length) {
    return stepIndex(param) > 0;
  }

  return param.value.base > param.min.base;
}

/** Цена следующей ступени (табличной или по единице). */
function nextStepCost(param: EditorAbilityParameter): number {
  if (param.steps.length) {
    const index = stepIndex(param);
    const next = param.steps[index + 1] ?? (index < 0 ? param.steps[0] : undefined);
    if (!next) return 0;
    const previous = index < 0 ? 0 : (param.steps[index]?.cost ?? 0);

    return Math.max(0, next.cost - previous);
  }

  return param.perUnit;
}

/** Цена текущей ступени (возврат при шаге вниз). */
function prevStepCost(param: EditorAbilityParameter): number {
  if (param.steps.length) {
    const index = stepIndex(param);
    if (index <= 0) return 0;
    const current = param.steps[index]?.cost ?? 0;
    const previous = param.steps[index - 1]?.cost ?? 0;

    return Math.max(0, current - previous);
  }

  return param.perUnit;
}

function stepExtraUp(row: ExtraParameterRow): void {
  const param = row.parameter;
  if (!canStepUpExtra(param)) return;
  if (param.steps.length) {
    const index = stepIndex(param);
    const next = index < 0 ? param.steps[0] : param.steps[index + 1];
    if (next) emit('set-parameter', row.ability.ruleCode, param.code, next.value);

    return;
  }
  emit('set-parameter', row.ability.ruleCode, param.code, param.value.base + 1);
}

function stepExtraDown(row: ExtraParameterRow): void {
  const param = row.parameter;
  if (!canStepDownExtra(param)) return;
  if (param.steps.length) {
    const index = stepIndex(param);
    const previous = param.steps[index - 1];
    if (previous) emit('set-parameter', row.ability.ruleCode, param.code, previous.value);

    return;
  }
  emit('set-parameter', row.ability.ruleCode, param.code, param.value.base - 1);
}

function extraValueLabel(param: EditorAbilityParameter): string {
  if (param.value.base === 0) return '—';

  return param.steps.length ? valueLabel(param.value) : String(param.value.base);
}

function costAt(param: EditorAbilityParameter, value: { base: number; size: number }): number {
  const cost = param.steps.find((step) => sameStep(step.value, value))?.cost ?? param.costs?.[valueLabel(value)] ?? 0;

  return Math.max(0, cost - param.freeStepCost);
}

function select(ability: EditorAbility, value: { base: number; size: number }): void {
  const param = paramOf(ability);
  if (!param) return;
  const chosen = isChosen(param, value);
  emit('set-parameter', ability.ruleCode, param.code, chosen ? 0 : value);
}
</script>

<template>
  <div class="innate-block">
    <template v-if="innateAbilities.length">
      <div class="innate-title">
        <v-icon icon="mdi-tune-variant" size="16" class="mr-1" />
        Врождённые черты характеристик
      </div>

      <v-table v-if="modifierRows.length" density="compact" class="innate-table">
        <thead>
          <tr>
            <th class="innate-name-col">Модификатор</th>
            <th class="text-center">Итог</th>
            <th v-for="step in modifierSteps" :key="valueLabel(step.value)" class="text-center">
              {{ valueLabel(step.value) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in modifierRows" :key="row.ability.ruleCode">
            <td>
              <span class="font-weight-medium">{{ row.name }}</span>
              <v-chip v-if="row.current !== 0" size="x-small" variant="tonal" color="primary" class="ml-1">
                {{ row.current }}
              </v-chip>
            </td>
            <td class="text-center font-weight-medium">{{ row.total }}</td>
            <td v-for="step in modifierSteps" :key="valueLabel(step.value)" class="text-center pa-1">
              <button
                type="button"
                class="purchase-cell"
                :class="{ active: isChosen(row.parameter, step.value) }"
                :disabled="stepDisabled(row.parameter, step.value)"
                :title="`${row.name} ${valueLabel(step.value)}: ${costAt(row.parameter, step.value)} ОС`"
                @click="select(row.ability, step.value)"
              >
                {{ costAt(row.parameter, step.value) }}
              </button>
            </td>
          </tr>
        </tbody>
      </v-table>

      <v-table v-if="giftRows.length" density="compact" class="innate-table mt-4">
        <thead>
          <tr>
            <th class="innate-name-col">Дар</th>
            <th class="text-center">Значение</th>
            <th v-for="step in giftSteps" :key="valueLabel(step.value)" class="text-center">
              {{ valueLabel(step.value) }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in giftRows" :key="row.ability.ruleCode">
            <td>
              <div class="font-weight-medium">{{ row.name }}</div>
              <div v-if="row.subtitle" class="text-caption text-medium-emphasis">{{ row.subtitle }}</div>
            </td>
            <td class="text-center font-weight-medium">{{ row.total }}</td>
            <td v-for="step in giftSteps" :key="valueLabel(step.value)" class="text-center pa-1">
              <button
                type="button"
                class="purchase-cell"
                :class="{ active: isChosen(row.parameter, step.value) }"
                :disabled="stepDisabled(row.parameter, step.value)"
                :title="`${row.name} ${valueLabel(step.value)}: ${costAt(row.parameter, step.value)} ОС`"
                @click="select(row.ability, step.value)"
              >
                {{ costAt(row.parameter, step.value) }}
              </button>
            </td>
          </tr>
        </tbody>
      </v-table>

      <v-table v-if="extraParameterRows.length" density="compact" class="innate-table mt-4">
        <thead>
          <tr>
            <th class="innate-name-col">Параметр</th>
            <th class="text-center">Значение</th>
            <th class="text-center">Ступень</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in extraParameterRows" :key="`${row.ability.ruleCode}:${row.parameter.code}`">
            <td class="font-weight-medium">{{ row.label }}</td>
            <td class="text-center font-weight-medium">{{ extraValueLabel(row.parameter) }}</td>
            <td class="text-center">
              <div class="d-flex align-center justify-center ga-1">
                <button
                  type="button"
                  class="purchase-cell"
                  :disabled="!canStepDownExtra(row.parameter)"
                  :title="`Уменьшить: ${prevStepCost(row.parameter)} ОС`"
                  @click="stepExtraDown(row)"
                >
                  − {{ prevStepCost(row.parameter) }}
                </button>
                <button
                  type="button"
                  class="purchase-cell"
                  :disabled="!canStepUpExtra(row.parameter)"
                  :title="`Увеличить: ${nextStepCost(row.parameter)} ОС`"
                  @click="stepExtraUp(row)"
                >
                  + {{ nextStepCost(row.parameter) }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </v-table>
    </template>

    <div v-else class="text-medium-emphasis pa-4">Врождённых черт характеристик нет.</div>
  </div>
</template>

<style scoped>
.innate-block {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  padding: 12px;
}

.innate-title {
  display: flex;
  align-items: center;
  font-size: 13px;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.78);
  margin-bottom: 8px;
}

.innate-name-col {
  min-width: 180px;
}

.purchase-cell {
  min-width: 40px;
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
