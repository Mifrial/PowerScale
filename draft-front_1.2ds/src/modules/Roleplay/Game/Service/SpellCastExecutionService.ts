import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpellCastExecutionInput } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionInput';
import type { SpellCastExecutionResult } from '@/modules/Roleplay/Game/Dto/Spell/SpellCastExecutionResult';
import type { ApplyAttackDamageInput } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageInput';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { keywordExperienceService } from '@/modules/Roleplay/Character/init';
import { spellDamageService, damageTypeSpecService } from '@/modules/Roleplay/Rule/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';
import { spellCastService } from '@/modules/Roleplay/Game/Service/Instance/spellCastService';
import { actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';
import { SIMPLE_TOUCH_CODE } from '@/modules/Roleplay/Game/Constant/Combat/SIMPLE_TOUCH_CODE';
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
    >,
  ): number {
    if (input.chargeSpendCost != null) {
      return input.chargeSpendCost;
    }
    const spellOd = actionOdCost(this.spellSpec(input.spellCode, input.rules)?.action_components);
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
    const touchOd = this.needsTouchAttack(input.spellCode, input.rules) ? actionOdCost(touchComponents) : 0;
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

  execute(input: SpellCastExecutionInput): SpellCastExecutionResult {
    const cost = this.actionPointCost(input);
    if (cost > input.currentActionPoints.base) {
      return this.refused('not_enough_ap');
    }
    const remaining = attackDamageService.spendActionPoints(input.currentActionPoints, cost);
    const spec = this.spellSpec(input.spellCode, input.rules);
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
      const touch = this.resolveAirTouch(input.touchActionCode === SIMPLE_TOUCH_CODE);
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

    const cast = spellCastService.rollForSpell(
      input.resolve,
      input.checkCode,
      input.characteristicValue,
      input.characteristicName,
      input.casterKey,
      input.rng,
      input.rules,
      input.mechanics,
      this.castAdvantages(input),
    );
    const castOk = !cast.needsCheck || Boolean(cast.roll?.check?.passed);
    const spellSr = this.spellSuccessRating(resolution.type, attackSr, milk, castOk);
    let spellApply: ApplyAttackDamageResult | null = null;
    let spellDamage: DimensionalNumberValue | null = null;
    if (castOk && spellSr !== null && spec?.spell.damage) {
      const amount = this.spellDamageAmount(spec, input);
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
      cast,
    };
  }

  completeAfterHit(
    input: SpellCastExecutionInput,
    delivery: { milk: boolean; attackSr: number },
  ): SpellCastExecutionResult {
    const spec = this.spellSpec(input.spellCode, input.rules);
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
    const cast = spellCastService.rollForSpell(
      input.resolve,
      input.checkCode,
      input.characteristicValue,
      input.characteristicName,
      input.casterKey,
      input.rng,
      input.rules,
      input.mechanics,
      this.castAdvantages(input),
    );
    const castOk = !cast.needsCheck || Boolean(cast.roll?.check?.passed);
    const spellSr = this.spellSuccessRating(resolution.type, delivery.attackSr, delivery.milk, castOk);
    let spellApply: ApplyAttackDamageResult | null = null;
    let spellDamage: DimensionalNumberValue | null = null;
    if (castOk && spellSr !== null && spec?.spell.damage) {
      const amount = this.spellDamageAmount(spec, input);
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
      cast,
    };
  }

  private spellDamageAmount(
    spec: Extract<AbilitySpec, { type: 'spell' }>,
    input: SpellCastExecutionInput,
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
    const amount = spellDamageService.amountFromPower(input.parameterPower, modify);
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

  private resolveAirTouch(isSimpleTouch: boolean): {
    hit: SpellCastExecutionResult['hit'];
    attackSr: number;
    milk: boolean;
  } {
    return { hit: null, attackSr: isSimpleTouch ? 1 : 0, milk: true };
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
