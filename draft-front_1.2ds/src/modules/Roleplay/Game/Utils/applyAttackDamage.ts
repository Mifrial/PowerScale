import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { DefenseLineOverview, DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';
import type { AttackResistanceLayer } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO,
  DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND,
  DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
} from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';
import { applyHooksOf, attackHooksOf } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';

export const ACTION_POINTS_CODE = 'action-points';
export const DEFAULT_ATTACK_AP = 3;

export function defenseApCost(reaction: HitDefenseReaction | null): number {
  if (reaction === 'dodge') return 1;
  if (reaction === 'block') return 2;

  return 0;
}

export function hasPaySrHook(hooks: DamageTypeHook[]): boolean {
  return attackHooksOf(hooks).some((hook) => hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR);
}

export function actionPointsResource(overview: CharacterOverview, rules: Rule[]): ResourceOverview | null {
  for (const resource of overview.resources) {
    if (rules.find((rule) => rule.id === resource.ruleId)?.code === ACTION_POINTS_CODE) return resource;
  }

  return null;
}

export function enduranceOf(overview: CharacterOverview, rules: Rule[]): number {
  for (const characteristic of overview.characteristics) {
    if (rules.find((rule) => rule.id === characteristic.ruleId)?.code === 'endurance') {
      return Math.max(1, characteristic.value.base);
    }
  }

  return 1;
}

export function spendActionPoints(current: DimensionalNumberValue, cost: number): DimensionalNumberValue {
  return { ...current, base: Math.max(0, current.base - Math.max(0, cost)) };
}

function lineMatchesType(line: DefenseLineOverview, damageTypeCode: string | null): boolean {
  if (line.kind === 'defense' || line.damageTypeCode === null || line.damageTypeCode === undefined) return true;

  return damageTypeCode !== null && line.damageTypeCode === damageTypeCode;
}

export function resistanceLayersOf(
  defense: DefenseOverview | null,
  damageTypeCode: string | null,
  ignoreAtMostDurability: number,
  includeDefense: boolean,
): AttackResistanceLayer[] {
  const layers: AttackResistanceLayer[] = [];
  for (const armor of defense?.armor ?? []) {
    for (const line of armor.lines) {
      if (line.kind !== 'defense' && line.kind !== 'resistance') continue;
      if (!lineMatchesType(line, damageTypeCode)) continue;
      const base = {
        itemName: armor.itemName,
        kind: line.kind,
        value: line.value,
        durability: line.durability,
        sourceLabel: line.sourceLabel,
      };
      if (line.kind === 'defense' && !includeDefense) {
        layers.push({ ...base, ignored: true, reason: 'defense_flag' });
        continue;
      }
      if (line.durability <= ignoreAtMostDurability) {
        layers.push({ ...base, ignored: true, reason: 'sr' });
        continue;
      }
      layers.push({ ...base, ignored: false, reason: 'kept' });
    }
  }

  return layers;
}

/**
 * Сопротивление типу урона: линии resistance (и defense, если includeDefense),
 * надёжность > ignoreAtMostDurability, нетипированные — ко всем; из одного sourceCode — максимум, затем сумма.
 */
export function stackedResistance(
  lines: DefenseLineOverview[],
  damageTypeCode: string | null,
  ignoreAtMostDurability: number,
  includeDefense = false,
): number {
  const kept = lines.filter((line) => {
    if (line.kind === 'defense') {
      if (!includeDefense) return false;
    } else if (line.kind !== 'resistance') return false;
    if (line.durability <= ignoreAtMostDurability) return false;
    if (line.kind === 'defense' || line.damageTypeCode === null || line.damageTypeCode === undefined) return true;

    return damageTypeCode !== null && line.damageTypeCode === damageTypeCode;
  });
  const bySource = new Map<string, number>();
  let ungrouped = 0;
  for (const line of kept) {
    if (!line.sourceCode) {
      ungrouped += line.value;
      continue;
    }
    bySource.set(line.sourceCode, Math.max(bySource.get(line.sourceCode) ?? 0, line.value));
  }
  let sum = ungrouped;
  for (const value of bySource.values()) sum += value;

  return sum;
}

export interface ApplyAttackDamageInput {
  weaponDamage: DimensionalNumberValue;
  sr: number;
  damageTypeCode: string | null;
  defense: DefenseOverview | null;
  endurance: number;
  hooks: DamageTypeHook[];
  /** Тип урона с галочкой «Защита не помогает» — линии defense не складываются в сопротивление. */
  defenseIgnored?: boolean;
}

export interface ApplyAttackDamageResult {
  remainingSr: number;
  resistance: number;
  raw: number;
  hpDamage: number;
  exhaustion: number;
  stun: number | null;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
  layers: AttackResistanceLayer[];
}

export function applyAttackDamage(input: ApplyAttackDamageInput): ApplyAttackDamageResult {
  const remainingSr = Math.max(0, Math.floor(input.sr));
  const ignoreAtMost = hasPaySrHook(input.hooks) ? remainingSr : 0;
  const includeDefense = !input.defenseIgnored;
  const layers = resistanceLayersOf(input.defense, input.damageTypeCode, ignoreAtMost, includeDefense);
  const lines = input.defense?.armor.flatMap((armor) => armor.lines) ?? [];
  const resistance = stackedResistance(lines, input.damageTypeCode, ignoreAtMost, includeDefense);
  const weapon = new DimensionalNumber(input.weaponDamage).toNumber();
  const raw = Math.max(0, weapon - resistance) * remainingSr;
  const apply = applyHooksOf(input.hooks);
  const cutting = apply.some((hook) => hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS);
  const hpDamage = cutting ? 0 : raw;
  const endurance = Math.max(1, Math.floor(input.endurance));
  const exhaustion = Math.floor(hpDamage / endurance);
  let stun: number | null = null;
  let wound: number | null = null;
  for (const hook of apply) {
    if (hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN && exhaustion > 0) stun = exhaustion;
    if (hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND && exhaustion > 0) {
      wound = exhaustion * (hook.woundMultiplier ?? 1);
    }
  }
  const knockout =
    apply.some((hook) => hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO) &&
    (remainingSr >= 6 || hpDamage >= endurance);

  return {
    remainingSr,
    resistance,
    raw,
    hpDamage,
    exhaustion,
    stun,
    wound,
    knockout,
    cuttingWound: cutting ? raw : null,
    layers,
  };
}
