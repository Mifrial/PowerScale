import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/init';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { ROLL_RULE_CODE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_RULE_CODE';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';
import { CHECK_INITIATIVE_CODE } from '@/modules/Roleplay/Rule/init';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';

/** Способ определения инициативы участника в окне проверки. */
export type InitiativeRollMethod = 'characteristic' | 'free' | 'fixed';

/** Параметры пула проверки из правила «Бросок» ревизии (фолбэк: 6 граней / сл. 3 / 3 куба). */
export interface InitiativeRollPoolDefaults {
  dieFaces: number;
  efficiency: number;
  freeDiceCount: number;
}

export function rollPoolDefaults(rules: Rule[]): InitiativeRollPoolDefaults {
  const rule = rules.find((candidate) => candidate.code === ROLL_RULE_CODE);
  const data = rule?.mechanic_payload?.type === 'roll' ? rule.mechanic_payload.data : undefined;

  return {
    dieFaces: data?.dieFaces ?? 6,
    efficiency: data?.efficiency ?? 3,
    freeDiceCount: data?.diceCount ?? 3,
  };
}

export interface InitiativeRollEntry {
  participant: GameInitiativeParticipant;
  method: InitiativeRollMethod;
  /** characteristic: движковое значение характеристики (до модификатора). */
  characteristicValue?: DimensionalNumberValue;
  /** characteristic: код выбранной характеристики → проверка `check-{code}` для механик броска. */
  characteristicCode?: string;
  /** characteristic: системный модификатор (CharacteristicNumber.modifyWith). */
  modifier?: number;
  /** characteristic/free: преимущества (>0) / помехи (<0). */
  adv?: number;
  /** fixed: фиксированное значение (без броска). */
  fixedValue?: number;
  /** Грани/сложность пула проверки (из правила «Бросок» ревизии). */
  dieFaces: number;
  efficiency: number;
  /** Число кубов свободного броска (из правила «Бросок» ревизии). */
  freeDiceCount: number;
}

export interface InitiativeRollResult {
  participant: GameInitiativeParticipant;
  /** Размерный итог броска (успехи + dieSize) или фикс. {n|0}. Порядок — dimensional compare. */
  value: DimensionalNumberValue;
  result: DiceRollResult | null;
}

function actorKeyOf(participant: GameInitiativeParticipant): CombatEntityKey | undefined {
  if (participant.entityId == null) return undefined;

  return participant.kind === 'npc' ? `npc:${participant.entityId}` : `character:${participant.entityId}`;
}

function characteristicSpec(entry: InitiativeRollEntry): DiceRollSpec {
  if (!entry.characteristicValue) {
    throw new Error(`Нет значения характеристики для ${entry.participant.name}`);
  }
  const value = CharacteristicNumber.from(entry.characteristicValue).modifyWith(entry.modifier ?? 0).value;
  const pool = Math.max(1, value.base);

  return {
    diceCount: pool,
    dieFaces: entry.dieFaces,
    efficiency: entry.efficiency,
    advantages: aggregateSourceDeltasService.advantageEntries(entry.adv ?? 0),
    dieSize: value.size,
    poolSize: value.size,
    efficiencySize: 0,
    label: entry.participant.name,
    actorKey: actorKeyOf(entry.participant),
  };
}

/** Спека проверки участника: характеристика (движковое значение) или свободный бросок. */
function entrySpec(entry: InitiativeRollEntry): DiceRollSpec {
  if (entry.method === 'characteristic') return characteristicSpec(entry);

  return {
    diceCount: entry.freeDiceCount,
    dieFaces: entry.dieFaces,
    efficiency: entry.efficiency,
    advantages: aggregateSourceDeltasService.advantageEntries(entry.adv ?? 0),
    dieSize: 0,
    poolSize: 0,
    efficiencySize: 0,
    label: entry.participant.name,
    actorKey: actorKeyOf(entry.participant),
  };
}

/**
 * Проверка на инициативу каждого участника; результат нужен только для порядка (не хранится).
 * Запуск — check-initiative. Механики броска — с проверки выбранной характеристики
 * (дефолт восприятие); свободный бросок — с check-initiative.
 */
export function rollInitiative(
  entries: InitiativeRollEntry[],
  rng: DiceRng = Math.random,
  rules: Rule[] = [],
  mechanics: Mechanic[] = [],
): InitiativeRollResult[] {
  const withMechanics = rules.length > 0 && mechanics.length > 0;

  return entries.map((entry) => {
    if (entry.method === 'fixed') {
      return { participant: entry.participant, value: { base: entry.fixedValue ?? 0, size: 0 }, result: null };
    }
    const spec = entrySpec(entry);
    const checkCode =
      entry.method === 'characteristic'
        ? checkResolutionService.resolveCheckCodeForCharacteristic(entry.characteristicCode, rules)
        : CHECK_INITIATIVE_CODE;
    const attachedRuleCodes = checkResolutionService.resolveCheckAttachedRuleCodes(checkCode, rules);
    const result = withMechanics
      ? rollEngine.roll(spec, rng, rules, mechanics, attachedRuleCodes, [])
      : rollService.computeRollResult(spec, rng);

    return {
      participant: entry.participant,
      value: { base: result.totalSuccesses, size: result.spec.dieSize || 0 },
      result,
    };
  });
}

function shuffle<T>(arr: T[], rng: DiceRng): void {
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(rng() * (i + 1));
    const tmp = arr[i];
    arr[i] = arr[j];
    arr[j] = tmp;
  }
}

/**
 * Порядок хода: по убыванию размерного итога (3↓ < 2), при равенстве — случайный порядок,
 * замораживается (сохраняется как порядок шкалы).
 */
export function orderInitiative(
  results: InitiativeRollResult[],
  rng: DiceRng = Math.random,
): GameInitiativeParticipant[] {
  const sorted = [...results].sort((left, right) =>
    new DimensionalNumber(right.value).compare(new DimensionalNumber(left.value)),
  );
  const ordered: InitiativeRollResult[] = [];
  let index = 0;
  while (index < sorted.length) {
    let end = index;
    while (
      end + 1 < sorted.length &&
      new DimensionalNumber(sorted[end + 1].value).equals(new DimensionalNumber(sorted[index].value))
    ) {
      end++;
    }
    const group = sorted.slice(index, end + 1);
    if (group.length > 1) shuffle(group, rng);
    ordered.push(...group);
    index = end + 1;
  }

  return ordered.map((result) => result.participant);
}
