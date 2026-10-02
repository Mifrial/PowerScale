import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpellCastExecutionInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionInput';
import type { SpellCastExecutionResult } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionResult';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { ApplyAttackDamageInput } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageInput';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { SpellCastRollOutcome } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastRollOutcome';
import { abilityCheckAdvantagesService } from '@/modules/Roleplay/Character/init';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { keywordExperienceService, magicStudyUnlockService } from '@/modules/Roleplay/Character/init';
import { spellDamageService, damageTypeSpecService } from '@/modules/Roleplay/Rule/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastService } from '@/modules/Roleplay/Game/Service/Instance/spellCastService';
import { actionOdCost, turnResourceCode } from '@/modules/Roleplay/Game/Utils/combatActions';
import { SPELL_CAST_SKIP_DIFFICULTY } from '@/modules/Roleplay/Game/Constant/Spell/SPELL_CAST_SKIP_DIFFICULTY';
import { spellCastUpgradeService } from '@/modules/Roleplay/Game/Service/Instance/spellCastUpgradeService';

/** Каст: max ОД, касание-атака, check или auto-fail, молоко и урон. */
export class SpellCastExecutionService {
  actionPointCost(
    input: Pick<
      SpellCastExecutionInput,
      | 'spellCode'
      | 'touchActionCode'
      | 'rules'
      | 'appliedUpgradeCodes'
      | 'casterAbilities'
      | 'pathCode'
      | 'chargeSpendCost'
    > & {
      casterOverview?: SpellCastExecutionInput['casterOverview'];
    },
  ): number {
    if (input.chargeSpendCost != null) {
      return input.chargeSpendCost;
    }
    const pool = turnResourceCode(input.rules);
    const actionPointLimit =
      input.casterOverview?.resources.find((resource) => resource.ruleCode === pool)?.max.base ?? 0;
    const spellOd = actionOdCost(
      this.spellSpec(input.spellCode, input.rules)?.action_components,
      actionPointLimit,
      pool,
    );
    const touchRule = input.touchActionCode
      ? input.rules.find((rule) => rule.code === input.touchActionCode)
      : undefined;
    const touchSpec =
      touchRule?.type === 'ability' && touchRule.spec && typeof touchRule.spec === 'object'
        ? (touchRule.spec as AbilitySpec)
        : null;
    const touchComponents =
      touchSpec && touchSpec.type !== 'group' && 'action_components' in touchSpec
        ? touchSpec.action_components
        : undefined;
    const touchOd = this.needsTouchAttack(input.spellCode, input.rules)
      ? actionOdCost(touchComponents, 0, pool)
      : 0;
    const selected = spellCastUpgradeService.selectedOf(
      spellCastUpgradeService.listApplicable(
        input.casterAbilities ?? [],
        input.pathCode ?? null,
        input.spellCode,
        input.rules,
      ),
      input.appliedUpgradeCodes ?? [],
    );

    return Math.max(spellOd + spellCastUpgradeService.actionPointDelta(selected), touchOd);
  }

  isSpellAvailableForUse(
    input: Pick<SpellCastExecutionInput, 'spellCode' | 'pathCode' | 'casterOverview' | 'casterAbilities' | 'rules'>,
  ): boolean {
    const spellSpec = this.spellSpec(input.spellCode, input.rules);
    if (!spellSpec) return true;

    return magicStudyUnlockService.isAvailableForUse(
      spellSpec,
      input.pathCode,
      input.casterAbilities,
      input.rules,
      new Map(
        input.casterOverview.characteristics.map((characteristic) => [characteristic.ruleCode, characteristic.value]),
      ),
    );
  }

  private castAdvantages(input: SpellCastExecutionInput) {
    return [
      ...spellCastUpgradeService.advantageModifiers(
        spellCastUpgradeService.selectedOf(
          spellCastUpgradeService.listApplicable(input.casterAbilities, input.pathCode, input.spellCode, input.rules),
          input.appliedUpgradeCodes,
        ),
      ),
      ...(input.extraCheckAdvantages ?? []),
    ];
  }

  rollCast(input: SpellCastExecutionInput): SpellCastRollOutcome {
    const selectedUpgrades = spellCastUpgradeService.selectedOf(
      spellCastUpgradeService.listApplicable(input.casterAbilities, input.pathCode, input.spellCode, input.rules),
      input.appliedUpgradeCodes,
    );
    const upgradeValues = input.appliedUpgradeValues ?? {};

    return spellCastService.rollForSpell(
      {
        ...input.resolve,
        requiredPowerDelta: spellCastUpgradeService.requiredPowerDelta(selectedUpgrades, upgradeValues),
        resistancePenetration: spellCastUpgradeService.resistancePenetration(selectedUpgrades, upgradeValues),
      },
      input.checkCode,
      input.castCheckCode,
      input.characteristicValue,
      input.characteristicName,
      input.casterKey,
      input.rng,
      input.rules,
      input.mechanics,
      this.castAdvantages(input),
      input.castCheckCode
        ? abilityCheckAdvantagesService.checkEfficiencyDeltasFromAbilities(
            { abilities: input.casterAbilities },
            input.rules,
            input.castCheckCode,
          )
        : [],
    );
  }

  saturationOf(
    input: Pick<SpellCastExecutionInput, 'casterAbilities' | 'rules'>,
  ): NonNullable<Extract<AbilitySpec, { spell_saturation?: unknown }>['spell_saturation']> | null {
    for (const ability of input.casterAbilities) {
      if (ability.level <= 0) continue;
      const spec = this.abilitySpecOf(ability.ruleCode, input.rules);
      const field = spec && 'spell_saturation' in spec ? spec.spell_saturation : undefined;
      if (!field || field.rating_per_step <= 0) continue;

      return field;
    }

    return null;
  }

  needsSaturationChoice(input: SpellCastExecutionInput, cast: SpellCastRollOutcome): boolean {
    const saturation = this.saturationOf(input);
    if (!saturation || !cast.needsCheck || !cast.roll?.check?.passed) return false;

    return (cast.roll.check.rating ?? 0) >= saturation.min_rating;
  }

  execute(input: SpellCastExecutionInput, preparedCast?: SpellCastRollOutcome): SpellCastExecutionResult {
    const spellSpec = this.spellSpec(input.spellCode, input.rules);
    if (!this.isSpellAvailableForUse(input)) {
      return this.refused('unavailable_spell');
    }
    const cost = this.actionPointCost(input);
    if (cost > input.currentActionPoints.base) {
      return this.refused('not_enough_ap');
    }
    const remaining = attackDamageService.spendActionPoints(input.currentActionPoints, cost);
    const spec = spellSpec;
    const resolution = spec?.hit_resolution ?? { type: 'none' };
    const autoFail = resolution.type === 'auto' && !input.resolve.hasTarget;
    let hit: SpellCastExecutionResult['hit'] = null;
    let attackSr: number | null = null;
    let milk = false;
    let delivery: SpellCastExecutionResult['delivery'] = 'none';
    const weaponApply: ApplyAttackDamageResult | null = null;

    if (resolution.type === 'attack') {
      if (input.touchTargetKey) {
        return this.refused('needs_hit_offer');
      }
      const touch = this.resolveAirTouch();
      hit = touch.hit;
      attackSr = touch.attackSr;
      milk = touch.milk;
      delivery = 'milk';
    } else if (resolution.type === 'auto') {
      delivery = autoFail ? 'none' : 'auto';
      attackSr = autoFail ? null : resolution.rating;
      milk = false;
    }

    const spentBase: Omit<SpellCastExecutionResult, 'cast' | 'spellApply' | 'spellSr' | 'autoFail'> = {
      started: true,
      refuseReason: null,
      spentAp: cost,
      remainingActionPoints: remaining,
      milk,
      delivery,
      hit,
      attackSr,
      spellDamage: null,
      weaponApply,
    };

    if (autoFail) {
      return {
        ...spentBase,
        autoFail: true,
        milk: false,
        spellSr: null,
        spellApply: null,
        cast: { difficulty: SPELL_CAST_SKIP_DIFFICULTY, needsCheck: true, roll: null },
      };
    }

    const cast = preparedCast ?? this.rollCast(input);
    const saturation = this.applySaturation(input, cast);
    if (!saturation.ok) {
      return this.refused('invalid_saturation');
    }
    const castOk = !cast.needsCheck || Boolean(cast.roll?.check?.passed);
    const spellSr = this.spellSuccessRating(resolution.type, attackSr, milk, castOk);
    let spellApply: ApplyAttackDamageResult | null = null;
    let spellDamage: DimensionalNumberValue | null = null;
    if (castOk && spellSr !== null && spec?.spell.damage) {
      const amount = this.spellDamageAmount(spec, input, saturation.power);
      if (amount) {
        spellDamage = amount;
        spellApply = this.applyTypedDamage(
          amount,
          spellSr,
          spec.spell.damage.damage_type_code,
          input.effectTargetOverview,
          input.rules,
          input.mechanics,
        );
      }
    }

    return {
      ...spentBase,
      autoFail: false,
      spellSr,
      spellDamage,
      spellApply,
      pendingEffectsAfterCast: this.pendingEffectsAfterSuccessfulCast(
        input,
        castOk,
        cast,
        cost,
        saturation.remainingRating,
      ),
      cast,
    };
  }

  completeAfterHit(
    input: SpellCastExecutionInput,
    delivery: { milk: boolean; attackSr: number },
    preparedCast?: SpellCastRollOutcome,
  ): SpellCastExecutionResult {
    const spec = this.spellSpec(input.spellCode, input.rules);
    if (!this.isSpellAvailableForUse(input)) {
      return this.refused('unavailable_spell');
    }
    const resolution = spec?.hit_resolution ?? { type: 'none' };
    const spentBase: Omit<SpellCastExecutionResult, 'cast' | 'spellApply' | 'spellSr' | 'autoFail'> = {
      started: true,
      refuseReason: null,
      spentAp: 0,
      remainingActionPoints: input.currentActionPoints,
      milk: delivery.milk,
      delivery: delivery.milk ? 'milk' : 'hit',
      hit: null,
      attackSr: delivery.attackSr,
      spellDamage: null,
      weaponApply: null,
    };
    const cast = preparedCast ?? this.rollCast(input);
    const saturation = this.applySaturation(input, cast);
    if (!saturation.ok) {
      return this.refused('invalid_saturation');
    }
    const castOk = !cast.needsCheck || Boolean(cast.roll?.check?.passed);
    const spellSr = this.spellSuccessRating(resolution.type, delivery.attackSr, delivery.milk, castOk);
    let spellApply: ApplyAttackDamageResult | null = null;
    let spellDamage: DimensionalNumberValue | null = null;
    if (castOk && spellSr !== null && spec?.spell.damage) {
      const amount = this.spellDamageAmount(spec, input, saturation.power);
      if (amount) {
        spellDamage = amount;
        spellApply = this.applyTypedDamage(
          amount,
          spellSr,
          spec.spell.damage.damage_type_code,
          input.effectTargetOverview,
          input.rules,
          input.mechanics,
        );
      }
    }

    return {
      ...spentBase,
      autoFail: false,
      spellSr,
      spellDamage,
      spellApply,
      pendingEffectsAfterCast: this.pendingEffectsAfterSuccessfulCast(
        input,
        castOk,
        cast,
        this.actionPointCost(input),
        saturation.remainingRating,
      ),
      cast,
    };
  }

  private pendingEffectsAfterSuccessfulCast(
    input: SpellCastExecutionInput,
    castOk: boolean,
    cast: SpellCastExecutionResult['cast'],
    actionCost: number,
    remainingRating: number,
  ): PendingActionEffect[] {
    if (!castOk || !cast?.roll?.check) {
      return [];
    }

    const effects: PendingActionEffect[] = [];
    for (const ability of input.casterAbilities) {
      if (ability.level <= 0) continue;
      const spec = this.abilitySpecOf(ability.ruleCode, input.rules);
      const field = spec && 'next_cast_difficulty' in spec ? spec.next_cast_difficulty : undefined;
      if (!field || remainingRating < field.min_remaining_rating) continue;
      effects.push({
        sourceRuleCode: ability.ruleCode,
        effect: {
          type: 'next_spell_cast_difficulty',
          delta: field.delta,
          source_code: field.source_code,
          max_total_action_cost: actionCost,
        },
      });
    }

    return effects;
  }

  private applySaturation(
    input: SpellCastExecutionInput,
    cast: SpellCastRollOutcome,
  ): { ok: true; power: DimensionalNumberValue; remainingRating: number } | { ok: false } {
    const steps = input.saturationSteps ?? 0;
    if (!Number.isInteger(steps) || steps < 0) {
      return { ok: false };
    }
    const rating = cast.roll?.check?.rating ?? 0;
    if (steps === 0) {
      return { ok: true, power: input.parameterPower, remainingRating: rating };
    }
    const saturation = this.saturationOf(input);
    const passed = Boolean(cast.needsCheck && cast.roll?.check?.passed);
    if (!saturation || !passed || saturation.rating_per_step * steps > rating) {
      return { ok: false };
    }

    return {
      ok: true,
      power: new DimensionalNumber(input.parameterPower)
        .modify(steps * saturation.power_per_step, CHARACTERISTIC_BASE_RANGE)
        .value,
      remainingRating: rating - saturation.rating_per_step * steps,
    };
  }

  private abilitySpecOf(ruleCode: string, rules: Rule[]): AbilitySpec | null {
    const rule = rules.find((entry) => entry.code === ruleCode && entry.type === 'ability');
    if (!rule?.spec || typeof rule.spec !== 'object' || !('type' in rule.spec) || rule.spec.type === 'group') {
      return null;
    }

    return rule.spec;
  }

  private spellDamageAmount(
    spec: Extract<AbilitySpec, { type: 'spell' }>,
    input: SpellCastExecutionInput,
    power: DimensionalNumberValue,
  ): DimensionalNumberValue | null {
    const damage = spec.spell.damage;
    if (!damage) {
      return null;
    }
    const experience = keywordExperienceService.experienceOf(
      damage.experience_keyword_code,
      input.casterAbilities,
      input.rules,
      input.keywords,
      (ability, _rule, priced) => keywordExperienceService.zoneLadderCost(ability, priced),
    );
    const modify = spellDamageService.modifyForExperience(damage, experience);
    const amount = spellDamageService.amountFromPower(power, modify);
    if (damage.falloff) {
      return spellDamageService.applyFalloff(amount, input.distanceIpari, damage.falloff);
    }

    return amount;
  }

  private spellSuccessRating(
    resolution: 'attack' | 'auto' | 'none',
    attackSr: number | null,
    milk: boolean,
    castOk: boolean,
  ): number | null {
    if (!castOk || milk || resolution === 'none') {
      return null;
    }
    if (resolution === 'auto') {
      return attackSr;
    }
    if (attackSr === null) {
      return null;
    }

    return attackSr === 0 ? 1 : attackSr;
  }

  spellTouchOf(
    actionCode: string | null,
    rules: Rule[],
  ): NonNullable<Extract<AbilitySpec, { spell_touch?: unknown }>['spell_touch']> | null {
    if (!actionCode) return null;
    const spec = this.abilitySpecOf(actionCode, rules);
    if (!spec || !('spell_touch' in spec) || !spec.spell_touch) return null;

    return spec.spell_touch;
  }

  private resolveAirTouch(): {
    hit: SpellCastExecutionResult['hit'];
    attackSr: number;
    milk: boolean;
  } {
    return { hit: null, attackSr: 0, milk: true };
  }

  applySpellDamage(
    weaponDamage: DimensionalNumberValue,
    sr: number,
    damageTypeCode: string | null,
    defender: SpellCastExecutionInput['touchTargetOverview'],
    rules: SpellCastExecutionInput['rules'],
    mechanics: SpellCastExecutionInput['mechanics'],
  ): ApplyAttackDamageResult {
    return this.applyTypedDamage(weaponDamage, sr, damageTypeCode, defender, rules, mechanics);
  }

  private applyTypedDamage(
    weaponDamage: DimensionalNumberValue,
    sr: number,
    damageTypeCode: string | null,
    defender: SpellCastExecutionInput['touchTargetOverview'],
    rules: SpellCastExecutionInput['rules'],
    mechanics: SpellCastExecutionInput['mechanics'],
  ): ApplyAttackDamageResult {
    const typeRule = damageTypeCode
      ? rules.find((rule) => rule.code === damageTypeCode && rule.type === 'damage_type')
      : undefined;
    const typeSpec = damageTypeSpecService.asDamageTypeSpec(typeRule);
    const input: ApplyAttackDamageInput = {
      weaponDamage,
      sr,
      damageTypeCode,
      defense: defender?.defense ?? null,
      endurance: defender ? attackDamageService.enduranceValueOf(defender, rules) : { base: 1, size: 0 },
      hooks: damageTypeHooksService.resolveDamageTypeHooks(damageTypeCode, rules, mechanics),
      defenseIgnored: typeSpec?.defense_ignored === true,
      maxSuccessRating: typeSpec?.max_success_rating ?? null,
    };

    return attackDamageService.applyAttackDamage(input);
  }

  private needsTouchAttack(spellCode: string, rules: Rule[]): boolean {
    return this.spellSpec(spellCode, rules)?.hit_resolution?.type === 'attack';
  }

  private spellSpec(spellCode: string, rules: Rule[]): Extract<AbilitySpec, { type: 'spell' }> | null {
    return spellCastDifficultyService.asSpellAbilitySpec(rules.find((rule) => rule.code === spellCode));
  }

  private refused(reason: SpellCastExecutionResult['refuseReason']): SpellCastExecutionResult {
    return {
      started: false,
      refuseReason: reason,
      spentAp: 0,
      remainingActionPoints: null,
      autoFail: false,
      milk: false,
      delivery: 'none',
      hit: null,
      attackSr: null,
      spellSr: null,
      spellDamage: null,
      weaponApply: null,
      spellApply: null,
      cast: null,
    };
  }
}
