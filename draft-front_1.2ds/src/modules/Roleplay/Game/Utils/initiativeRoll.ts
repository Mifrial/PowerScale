import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { GameInitiativeParticipant } from '@/modules/Roleplay/Game/Dto/GameInitiative';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { rollService } from '@/modules/Roleplay/Game/Service/Instance/rollService';
import { rollEngine } from '@/modules/Roleplay/Game/Service/Roll/Instance/rollEngine';
import { ROLL_RULE_CODE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_RULE_CODE';

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
  /** Значение для сортировки: totalSuccesses броска или фикс. значение. */
  value: number;
  result: DiceRollResult | null;
}

function characteristicSpec(entry: InitiativeRollEntry): DiceRollSpec {
  // Модификатор — системный: меняет размерность характеристики, пул = toNumber() результата.
  const value = CharacteristicNumber.from(entry.characteristicValue ?? { base: 0, size: 0 }).modifyWith(
    entry.modifier ?? 0,
  );
  const pool = Math.max(1, new DimensionalNumber(value.value).toNumber());

  return {
    diceCount: pool,
    dieFaces: entry.dieFaces,
    efficiency: entry.efficiency,
    adv: entry.adv ?? 0,
    dieSize: 0,
    label: entry.participant.name,
  };
}

/** Спека проверки участника: характеристика (движковое значение) или свободный бросок. */
function entrySpec(entry: InitiativeRollEntry): DiceRollSpec {
  if (entry.method === 'characteristic') return characteristicSpec(entry);

  return {
    diceCount: entry.freeDiceCount,
    dieFaces: entry.dieFaces,
    efficiency: entry.efficiency,
    adv: entry.adv ?? 0,
    dieSize: 0,
    label: entry.participant.name,
  };
}

/**
 * Проверка на инициативу каждого участника; результат нужен только для порядка (не хранится).
 * С механиками ревизии (правила + механики) бросок идёт через RollEngine — инициатива
 * подчиняется тем же правилам подсчёта, что и обычные броски (6-и-1 и пр.).
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
      return { participant: entry.participant, value: entry.fixedValue ?? 0, result: null };
    }
    const spec = entrySpec(entry);
    const result = withMechanics
      ? rollEngine.roll(spec, rng, rules, mechanics)
      : rollService.computeRollResult(spec, rng);

    return { participant: entry.participant, value: result.totalSuccesses, result };
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
 * Порядок хода: по убыванию значения, при равных значениях — случайный порядок,
 * замораживается (сохраняется как порядок шкалы).
 */
export function orderInitiative(
  results: InitiativeRollResult[],
  rng: DiceRng = Math.random,
): GameInitiativeParticipant[] {
  const sorted = [...results].sort((a, b) => b.value - a.value);
  const ordered: InitiativeRollResult[] = [];
  let index = 0;
  while (index < sorted.length) {
    let end = index;
    while (end + 1 < sorted.length && sorted[end + 1].value === sorted[index].value) end++;
    const group = sorted.slice(index, end + 1);
    if (group.length > 1) shuffle(group, rng);
    ordered.push(...group);
    index = end + 1;
  }

  return ordered.map((result) => result.participant);
}
