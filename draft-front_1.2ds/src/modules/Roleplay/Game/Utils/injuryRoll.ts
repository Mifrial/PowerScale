import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { InjuryHealRoll, InjuryHealUnit, InjuryOutcome } from '@/modules/Roleplay/Game/Dto/InjuryOutcome';
import type { InjuryProcedure } from '@/modules/Roleplay/Game/Dto/InjuryProcedure';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_INJURY_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { ADVANTAGE_SOURCE_MANUAL } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { netSourceDelta } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';
import { injuryHooksOf, resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import { resolveInjuryProcedure } from '@/modules/Roleplay/Game/Utils/resolveInjuryProcedure';

export interface InjuryRollInput {
  damage: number;
  woundStrength: number;
  endurance: number;
  exhaustion: number;
  attackSr: number;
  damageTypeCode?: string | null;
  advantages?: AdvantageModifier[];
  actorKey?: DiceRollResult['spec']['actorKey'];
  label?: string;
}

export function injuryPoolSize(
  input: Pick<InjuryRollInput, 'damage' | 'woundStrength' | 'endurance' | 'exhaustion' | 'attackSr'>,
  procedure: InjuryProcedure,
  extraDice: number,
): number {
  const endurance = Math.max(1, Math.floor(input.endurance));
  const fromDamage = Math.floor(Math.max(0, input.damage) / endurance);
  const fromWound = Math.floor(Math.max(0, input.woundStrength) / procedure.woundDiceDivisor);
  const fromExhaustion =
    input.exhaustion >= procedure.exhaustionCheckMin
      ? Math.max(0, input.exhaustion - procedure.exhaustionDiceOffset)
      : 0;

  return Math.max(0, Math.max(fromDamage, fromWound, fromExhaustion) + extraDice);
}

function rollDie(rng: DiceRng, faces: number): number {
  return Math.floor(rng() * faces) + 1;
}

function explode(faces: number[], explodeFace: number, rng: DiceRng): number[] {
  const result = [...faces];
  let pending = faces.filter((face) => face === explodeFace).length;
  while (pending > 0) {
    let nextSixes = 0;
    for (let i = 0; i < pending; i += 1) {
      const rolled = rollDie(rng, 6);
      result.push(rolled);
      if (rolled === explodeFace) nextSixes += 1;
    }
    pending = nextSixes;
  }

  return result;
}

function applyAdvantages(rolls: number[], net: number): { kept: number[]; dropped: number[] } {
  const extra = Math.abs(net);
  if (extra === 0) return { kept: [...rolls], dropped: [] };
  const sorted = [...rolls];
  if (net > 0) sorted.sort((a, b) => a - b);
  else sorted.sort((a, b) => b - a);
  const dropped = sorted.slice(0, extra);
  const kept = [...rolls];
  for (const face of dropped) {
    const index = kept.indexOf(face);
    if (index >= 0) kept.splice(index, 1);
  }

  return { kept, dropped };
}

function healSpec(strength: number): { diceCount: number; dieFaces: number; unit: InjuryHealUnit } {
  if (strength <= 1) return { diceCount: 1, dieFaces: 6, unit: 'days' };
  if (strength === 2) return { diceCount: 2, dieFaces: 6, unit: 'days' };
  if (strength === 3) return { diceCount: 4, dieFaces: 6, unit: 'days' };
  if (strength === 4) return { diceCount: 1, dieFaces: 6, unit: 'decades' };
  if (strength === 5) return { diceCount: 1, dieFaces: 5, unit: 'months' };

  return { diceCount: 1, dieFaces: 6, unit: 'years' };
}

function classify(faces: number[]): Omit<InjuryOutcome, 'heal'> {
  const strength = faces.length;
  const fours = faces.filter((face) => face === 4).length;
  const fives = faces.filter((face) => face === 5).length;
  const sixes = faces.filter((face) => face === 6).length;

  return {
    strength,
    permanent: fives >= 2,
    temporary: strength > 0 && fives < 2,
    lethal: sixes >= 3,
    disfiguring: fours === 1,
  };
}

export function rollInjury(input: InjuryRollInput, rng: DiceRng, rules: Rule[], mechanics: Mechanic[]): DiceRollResult {
  const procedure = resolveInjuryProcedure(rules, mechanics);
  const hooks = injuryHooksOf(resolveDamageTypeHooks(input.damageTypeCode, rules, mechanics));
  let extraDice = 0;
  let efficiencyDelta = 0;
  for (const hook of hooks) {
    if (hook.extraDiceFromSrDivisor) {
      extraDice += Math.floor(Math.max(0, input.attackSr) / hook.extraDiceFromSrDivisor);
    }
    efficiencyDelta += hook.efficiencyDelta ?? 0;
  }
  const advantages = input.advantages ?? [];
  const net = netSourceDelta(advantages);
  const pool = injuryPoolSize(input, procedure, extraDice);
  const initial = Array.from({ length: pool + Math.abs(net) }, () => rollDie(rng, 6));
  const afterAdv = applyAdvantages(initial, net);
  const exploded = explode(afterAdv.kept, procedure.explodeFace, rng);
  const dropBelow = procedure.dropBelow + efficiencyDelta;
  const remaining = exploded.filter((face) => face >= dropBelow);
  const droppedLow = exploded.filter((face) => face < dropBelow);
  const outcome = classify(remaining);
  let heal: InjuryHealRoll | undefined;
  if (outcome.temporary && outcome.strength > 0) {
    const spec = healSpec(outcome.strength);
    const rolls = Array.from({ length: spec.diceCount }, () => rollDie(rng, spec.dieFaces));
    heal = {
      diceCount: spec.diceCount,
      dieFaces: spec.dieFaces,
      unit: spec.unit,
      rolls,
      total: rolls.reduce((sum, value) => sum + value, 0),
    };
  }
  const injury: InjuryOutcome = { ...outcome, heal };

  return {
    spec: {
      diceCount: pool + Math.abs(net),
      dieSize: 0,
      dieFaces: 6,
      efficiency: dropBelow,
      advantages,
      label: input.label ?? 'Проверка на увечье',
      actorKey: input.actorKey,
    },
    rolls: exploded,
    successes: remaining.map(() => 1),
    adjustedRolls: remaining,
    droppedRolls: [...afterAdv.dropped, ...droppedLow],
    totalSuccesses: injury.strength,
    appliedMechanics: hooks.map((hook) => hook.mechanicCode),
    check: {
      check_code: CHECK_INJURY_CODE,
      difficulty: { base: 0, size: 0 },
      passed: injury.strength > 0,
      rating: injury.strength,
    },
    injury,
  };
}

export function manualInjuryAdvantages(delta: number): AdvantageModifier[] {
  if (delta === 0) return [];

  return [{ source_code: ADVANTAGE_SOURCE_MANUAL, source_label: 'вручную', delta }];
}
