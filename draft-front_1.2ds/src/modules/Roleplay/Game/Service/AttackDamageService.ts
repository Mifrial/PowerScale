import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { DefenseLineOverview, DefenseOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';
import type { ResourceOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceOverview';
import type { DamageTypeHook } from '@/modules/Roleplay/Game/Dto/DamageTypeHook';
import type { AttackResistanceLayer } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  DAMAGE_TYPE_HOOK_MECHANIC_BLUNT_KO,
  DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_STUN,
  DAMAGE_TYPE_HOOK_MECHANIC_EXHAUSTION_WOUND,
  DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR,
} from '@/modules/Roleplay/Rule/init';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
import { ACCUMULATED_DAMAGE_STATE_CODE } from '@/modules/Roleplay/Rule/init';

import type { ApplyAttackDamageInput } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageInput';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
export class AttackDamageService {
  defenseApCost(reaction: HitDefenseReaction | null): number {
    if (reaction === 'dodge') return 1;
    if (reaction === 'block') return 2;

    return 0;
  }

  hasPaySrHook(hooks: DamageTypeHook[]): boolean {
    return damageTypeHooksService
      .attackHooksOf(hooks)
      .some((hook) => hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_PAY_SR);
  }

  actionPointsResource(overview: CharacterOverview, rules: Rule[]): ResourceOverview | null {
    for (const resource of overview.resources) {
      if (rules.find((rule) => rule.id === resource.ruleId)?.code === ACTION_POINTS_CODE) return resource;
    }

    return null;
  }

  enduranceValueOf(overview: CharacterOverview, rules: Rule[]): DimensionalNumberValue {
    for (const characteristic of overview.characteristics) {
      if (rules.find((rule) => rule.id === characteristic.ruleId)?.code === 'endurance') {
        return characteristic.value;
      }
    }

    return { base: 1, size: 0 };
  }

  enduranceOf(overview: CharacterOverview, rules: Rule[]): number {
    return Math.max(1, new DimensionalNumber(this.enduranceValueOf(overview, rules)).toNumber());
  }

  accumulatedDamageOf(states: CharacterStateValue[], rules: Rule[]): DimensionalNumberValue {
    const rule = rules.find(
      (candidate) => candidate.code === ACCUMULATED_DAMAGE_STATE_CODE && candidate.type === 'state',
    );
    if (!rule) return { base: 0, size: 0 };

    return states
      .filter((state) => state.stateRuleId === rule.id)
      .reduce(
        (total, state) => total.add(new DimensionalNumber(state.dimensionalValue ?? { base: 0, size: 0 })),
        new DimensionalNumber({ base: 0, size: 0 }),
      ).value;
  }

  spendActionPoints(current: DimensionalNumberValue, cost: number): DimensionalNumberValue {
    return { ...current, base: Math.max(0, current.base - Math.max(0, cost)) };
  }

  private lineMatchesType(line: DefenseLineOverview, damageTypeCode: string | null): boolean {
    if (line.kind === 'defense' || line.damageTypeCode === null || line.damageTypeCode === undefined) return true;

    return damageTypeCode !== null && line.damageTypeCode === damageTypeCode;
  }

  resistanceLayersOf(
    defense: DefenseOverview | null,
    damageTypeCode: string | null,
    ignoreAtMostDurability: number,
    includeDefense: boolean,
  ): AttackResistanceLayer[] {
    const layers: AttackResistanceLayer[] = [];
    for (const armor of defense?.armor ?? []) {
      for (const line of armor.lines) {
        if (line.kind !== 'defense' && line.kind !== 'resistance') continue;
        if (!this.lineMatchesType(line, damageTypeCode)) continue;
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
  stackedResistance(
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

  applyAttackDamage(input: ApplyAttackDamageInput): ApplyAttackDamageResult {
    const remainingSr = Math.max(0, Math.floor(input.sr));
    const ignoreAtMost = this.hasPaySrHook(input.hooks) ? remainingSr : 0;
    const includeDefense = !input.defenseIgnored;
    const layers = this.resistanceLayersOf(input.defense, input.damageTypeCode, ignoreAtMost, includeDefense);
    const lines = input.defense?.armor.flatMap((armor) => armor.lines) ?? [];
    const resistance = this.stackedResistance(lines, input.damageTypeCode, ignoreAtMost, includeDefense);
    const weapon = new DimensionalNumber(input.weaponDamage).toNumber();
    const raw = Math.max(0, weapon - resistance) * remainingSr;
    const apply = damageTypeHooksService.applyHooksOf(input.hooks);
    const cutting = apply.some((hook) => hook.mechanicCode === DAMAGE_TYPE_HOOK_MECHANIC_CUTTING_WOUNDS);
    const hpDamage = cutting ? 0 : raw;
    const enduranceValue =
      typeof input.endurance === 'number'
        ? { base: Math.max(1, Math.floor(input.endurance)), size: 0 }
        : input.endurance;
    const endurance = new DimensionalNumber(enduranceValue);
    const damageValue = new DimensionalNumber({ base: hpDamage, size: 0 });
    const accumulatedDamage = new DimensionalNumber(input.accumulatedDamage ?? { base: 0, size: 0 });
    const totalDamage = accumulatedDamage.add(damageValue);
    const previousExhaustion = accumulatedDamage.divideFloor(endurance);
    const totalExhaustion = totalDamage.divideFloor(endurance);
    const exhaustion = Math.max(0, totalExhaustion - previousExhaustion);
    const remainingHpDamage = Math.max(0, totalDamage.subtract(endurance.multiply(totalExhaustion)).toNumber());
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
      (remainingSr >= 6 || hpDamage >= endurance.toNumber());

    return {
      remainingSr,
      resistance,
      raw,
      hpDamage,
      exhaustion,
      remainingHpDamage,
      stun,
      wound,
      knockout,
      cuttingWound: cutting ? raw : null,
      layers,
    };
  }
}
