import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { actionEffectLabelService } from '@/modules/Roleplay/Rule/init';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';
import { characterHandsService } from '@/modules/Roleplay/Character/init';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';

export class ActionEffectService {
  effectsOf(rule: Rule | null | undefined): ActionEffect[] {
    const effects = rule?.spec && 'action_effects' in rule.spec ? rule.spec.action_effects : undefined;

    return effects ? [...effects] : [];
  }

  describe(effect: ActionEffect, sourceLabel?: string): string {
    return actionEffectLabelService.describe(effect, sourceLabel);
  }

  /** Подписи эффектов запуска атаки: действие, дети, удержание; только подходящие к профилю. */
  describeForLaunch(
    actionRule: Rule | null | undefined,
    actor: { inventory: InventoryItem[]; abilities: CharacterAbility[] } | null | undefined,
    attack:
      | {
          itemRuleCode: string;
          profileType: 'strike' | 'throw' | 'shoot';
          damageTypeCode: string | null;
        }
      | null
      | undefined,
    rules: Rule[],
    selectedChildCodes: readonly string[] = [],
  ): string[] {
    const occupyHands =
      actor && attack
        ? characterHandsService.occupyHandsForAttack(actor.inventory, attack.itemRuleCode, attack.profileType, rules)
        : 0;
    const context = { occupyHands, damageTypeCode: attack?.damageTypeCode ?? null };
    const lines: string[] = [];
    const pushEffect = (effect: ActionEffect, sourceLabel?: string): void => {
      if (
        effect.type === 'current_action_attack_characteristic_modifier' &&
        !this.matchesAttackContext(effect, context)
      ) {
        return;
      }
      lines.push(this.describe(effect, sourceLabel));
    };

    for (const effect of this.effectsOf(actionRule)) {
      pushEffect(effect);
    }
    for (const child of this.childAbilityRules(
      actionRule?.code,
      (actor?.abilities ?? []).filter((ability) => ability.level >= 1).map((ability) => ability.ruleCode),
      rules,
    )) {
      for (const effect of this.effectsOf(child)) {
        if (effect.type === 'optional_after_strike_check') {
          if (selectedChildCodes.includes(child.code)) pushEffect(effect, child.name);
          continue;
        }
        pushEffect(effect, child.name);
      }
    }
    const grip = characterHandsService.gripBonus(occupyHands);
    if (grip) {
      lines.push(`+${grip} к силе удара (удержание)`);
    }

    return lines;
  }

  effectsChatSuffix(
    actionRule: Rule | null | undefined,
    actor: { inventory: InventoryItem[]; abilities: CharacterAbility[] } | null | undefined,
    attack:
      | {
          itemRuleCode: string;
          profileType: 'strike' | 'throw' | 'shoot';
          damageTypeCode: string | null;
        }
      | null
      | undefined,
    rules: Rule[],
    selectedChildCodes: readonly string[] = [],
  ): string {
    const lines = this.describeForLaunch(actionRule, actor, attack, rules, selectedChildCodes);
    if (lines.length === 0) return '';

    return `\nЭффекты: ${lines.join('; ')}`;
  }

  currentAttackAccuracy(rule: Rule | null | undefined, component: 'strike' | 'throw' | 'shoot'): number {
    return this.effectsOf(rule)
      .filter(
        (effect): effect is Extract<ActionEffect, { type: 'current_action_attack_accuracy' }> =>
          effect.type === 'current_action_attack_accuracy' &&
          effect.scope.components.includes(component) &&
          this.scopeIncludesHit(effect.scope, 1),
      )
      .reduce((total, effect) => total + effect.delta, 0);
  }

  currentAttackActionCharacteristicModifier(
    rule: Rule | null | undefined,
    component: 'strike' | 'throw' | 'shoot',
    hitNumber = 1,
    context?: {
      occupyHands?: number;
      damageTypeCode?: string | null;
      extraEffects?: ActionEffect[];
    },
  ): number {
    return [...this.effectsOf(rule), ...(context?.extraEffects ?? [])]
      .filter(
        (effect): effect is Extract<ActionEffect, { type: 'current_action_attack_characteristic_modifier' }> =>
          effect.type === 'current_action_attack_characteristic_modifier' &&
          effect.scope.components.includes(component) &&
          this.scopeIncludesHit(effect.scope, hitNumber) &&
          this.matchesAttackContext(effect, context),
      )
      .reduce((total, effect) => total + effect.delta, 0);
  }

  childActionEffects(
    parentCode: string | null | undefined,
    ownedAbilityCodes: readonly string[],
    rules: Rule[],
  ): ActionEffect[] {
    return this.childAbilityRules(parentCode, ownedAbilityCodes, rules).flatMap((rule) => this.effectsOf(rule));
  }

  private childAbilityRules(
    parentCode: string | null | undefined,
    ownedAbilityCodes: readonly string[],
    rules: Rule[],
  ): Rule[] {
    if (!parentCode) return [];
    const owned = new Set(ownedAbilityCodes);
    const children: Rule[] = [];
    for (const rule of rules) {
      if (!owned.has(rule.code) || rule.type !== 'ability' || !rule.spec || !('parent_ability_code' in rule.spec)) {
        continue;
      }
      if (rule.spec.parent_ability_code !== parentCode) continue;
      children.push(rule);
    }

    return children;
  }

  optionalAfterStrikeOptions(
    parentCode: string | null | undefined,
    ownedAbilityCodes: readonly string[],
    rules: Rule[],
  ): { rule: Rule; effect: Extract<ActionEffect, { type: 'optional_after_strike_check' }> }[] {
    const options: { rule: Rule; effect: Extract<ActionEffect, { type: 'optional_after_strike_check' }> }[] = [];
    for (const rule of this.childAbilityRules(parentCode, ownedAbilityCodes, rules)) {
      for (const effect of this.effectsOf(rule)) {
        if (effect.type === 'optional_after_strike_check') options.push({ rule, effect });
      }
    }

    return options;
  }

  currentAttackActionCharacteristicModifierForActor(
    actionRule: Rule | null | undefined,
    component: 'strike' | 'throw' | 'shoot',
    actor: { inventory: InventoryItem[]; abilities: CharacterAbility[] } | null | undefined,
    attack:
      | {
          itemRuleCode: string;
          profileType: 'strike' | 'throw' | 'shoot';
          damageTypeCode: string | null;
        }
      | null
      | undefined,
    rules: Rule[],
    hitNumber = 1,
  ): number {
    const occupyHands =
      actor && attack
        ? characterHandsService.occupyHandsForAttack(actor.inventory, attack.itemRuleCode, attack.profileType, rules)
        : 0;

    return this.currentAttackActionCharacteristicModifier(actionRule, component, hitNumber, {
      occupyHands,
      damageTypeCode: attack?.damageTypeCode ?? null,
      extraEffects: this.childActionEffects(
        actionRule?.code,
        (actor?.abilities ?? []).filter((ability) => ability.level >= 1).map((ability) => ability.ruleCode),
        rules,
      ),
    });
  }

  successRatingAttackCharacteristicModifier(
    rule: Rule | null | undefined,
    successRating: number,
    component: 'strike' | 'throw' | 'shoot',
    hitNumber = 1,
  ): number {
    return this.effectsOf(rule)
      .filter(
        (
          effect,
        ): effect is Extract<ActionEffect, { type: 'current_action_attack_characteristic_from_success_rating' }> =>
          effect.type === 'current_action_attack_characteristic_from_success_rating' &&
          effect.scope.components.includes(component) &&
          this.scopeIncludesHit(effect.scope, hitNumber),
      )
      .reduce((total, effect) => {
        const step = effect.floor_div > 0 ? Math.floor(Math.max(0, successRating) / effect.floor_div) : 0;

        return total + Math.min(effect.cap, step);
      }, 0);
  }

  currentActionCheckModifier(rule: Rule | null | undefined, checkCode: string): number {
    return this.effectsOf(rule)
      .filter(
        (effect): effect is Extract<ActionEffect, { type: 'current_action_check_modifier' }> =>
          effect.type === 'current_action_check_modifier' && effect.check_codes.includes(checkCode),
      )
      .reduce((total, effect) => total + effect.delta, 0);
  }

  currentActionCheckModifiers(rule: Rule | null | undefined, checkCode: string): AdvantageModifier[] {
    const delta = this.currentActionCheckModifier(rule, checkCode);
    if (!delta) return [];

    return [{ source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES, source_label: 'Обстоятельства', delta }];
  }

  applyCurrentAttackActionCharacteristicModifier(
    rule: Rule | null | undefined,
    component: 'strike' | 'throw' | 'shoot',
    value: DimensionalNumberValue,
    hitNumber = 1,
  ): DimensionalNumberValue {
    const delta = this.currentAttackActionCharacteristicModifier(rule, component, hitNumber);

    return new DimensionalNumber(value).modify(delta, CHARACTERISTIC_BASE_RANGE).value;
  }

  effectsAfterAction(rule: Rule | null | undefined): PendingActionEffect[] {
    return this.effectsOf(rule)
      .filter(
        (effect) =>
          effect.type === 'next_action_attack_cost' ||
          effect.type === 'next_action_attack_target_characteristic_modifier' ||
          effect.type === 'after_action_until_resource_spent_check_modifier',
      )
      .map((effect) => ({ sourceRuleCode: rule?.code ?? '', effect }));
  }

  effectsAfterProcess(rule: Rule | null | undefined): PendingActionEffect[] {
    if (!rule || rule.type !== 'ability' || !rule.spec || !('type' in rule.spec) || rule.spec.type !== 'process')
      return [];

    return (rule.spec.process.completion_effects ?? []).map((effect) => ({
      sourceRuleCode: rule.code,
      effect,
    }));
  }

  resolveForNextAction(
    pendingEffects: PendingActionEffect[],
    action: {
      isAttack: boolean;
      component: 'strike' | 'throw' | 'shoot';
      baseCost: number;
      targetDexterityMastery?: number;
    },
  ): {
    actionCostDelta: number;
    targetDexterityMasteryDelta: number;
    targetDexterityMasteryAdjustments: { sourceRuleCode: string; delta: number }[];
    remainingEffects: PendingActionEffect[];
  } {
    const costDelta = pendingEffects
      .filter(
        (
          pending,
        ): pending is PendingActionEffect & {
          effect: Extract<ActionEffect, { type: 'next_action_attack_cost' }>;
        } => pending.effect.type === 'next_action_attack_cost' && action.isAttack,
      )
      .reduce((total, pending) => total + pending.effect.delta, 0);
    const finalCost = action.baseCost + costDelta;
    let targetDexterityMasteryDelta = 0;
    const targetDexterityMasteryAdjustments: { sourceRuleCode: string; delta: number }[] = [];
    const remainingEffects: PendingActionEffect[] = [];

    for (const pending of pendingEffects) {
      const effect = pending.effect;
      if (effect.type === 'after_action_until_resource_spent_check_modifier') {
        remainingEffects.push(pending);
        continue;
      }
      if (effect.type === 'next_action_attack_cost') continue;
      if (
        action.isAttack &&
        effect.type === 'next_action_attack_target_characteristic_modifier' &&
        effect.scope.components.includes(action.component) &&
        this.scopeIncludesHit(effect.scope, 1) &&
        (effect.max_total_action_cost === undefined || finalCost <= effect.max_total_action_cost) &&
        effect.check_code === 'melee-combat' &&
        effect.characteristic_code === 'dexterity'
      ) {
        const appliedDelta =
          effect.min === undefined
            ? effect.delta
            : Math.max(effect.min - ((action.targetDexterityMastery ?? 0) + targetDexterityMasteryDelta), effect.delta);
        targetDexterityMasteryDelta += appliedDelta;
        targetDexterityMasteryAdjustments.push({ sourceRuleCode: pending.sourceRuleCode, delta: appliedDelta });
      }
    }

    return {
      actionCostDelta: costDelta,
      targetDexterityMasteryDelta,
      targetDexterityMasteryAdjustments,
      remainingEffects,
    };
  }

  checkAdvantageDelta(pendingEffects: PendingActionEffect[], checkCode: string): number {
    return pendingEffects
      .filter(
        (
          pending,
        ): pending is PendingActionEffect & {
          effect: Extract<ActionEffect, { type: 'after_action_until_resource_spent_check_modifier' }>;
        } =>
          pending.effect.type === 'after_action_until_resource_spent_check_modifier' &&
          pending.effect.check_codes.includes(checkCode),
      )
      .reduce((total, pending) => total + pending.effect.delta, 0);
  }

  checkAdvantageModifiers(pendingEffects: PendingActionEffect[], checkCode: string): AdvantageModifier[] {
    return pendingEffects
      .filter(
        (
          pending,
        ): pending is PendingActionEffect & {
          effect: Extract<ActionEffect, { type: 'after_action_until_resource_spent_check_modifier' }>;
        } =>
          pending.effect.type === 'after_action_until_resource_spent_check_modifier' &&
          pending.effect.check_codes.includes(checkCode),
      )
      .map((pending) => ({
        source_code: ADVANTAGE_SOURCE_CIRCUMSTANCES,
        source_label: 'Обстоятельства',
        delta: pending.effect.delta,
      }));
  }

  consumeResource(pendingEffects: PendingActionEffect[], resourceCode: string, amount: number): PendingActionEffect[] {
    if (amount <= 0) return pendingEffects;

    return pendingEffects.flatMap((pending) => {
      const effect = pending.effect;
      if (effect.type !== 'after_action_until_resource_spent_check_modifier' || effect.resource_code !== resourceCode) {
        return [pending];
      }
      const remaining = effect.amount - amount;
      if (remaining <= 0) return [];

      return [{ ...pending, effect: { ...effect, amount: remaining } }];
    });
  }

  afterDeclaredAction(
    pendingEffects: PendingActionEffect[],
    spentOd: number,
    action: {
      isAttack: boolean;
      component: 'strike' | 'throw' | 'shoot';
      baseCost: number;
    },
  ): PendingActionEffect[] {
    const resolved = this.resolveForNextAction(pendingEffects, action);

    return this.consumeResource(resolved.remainingEffects, 'action-points', spentOd);
  }

  private scopeIncludesHit(scope: { hit_count: number | 'all' }, hitNumber: number): boolean {
    return scope.hit_count === 'all' || hitNumber <= scope.hit_count;
  }

  private matchesAttackContext(
    effect: Extract<ActionEffect, { type: 'current_action_attack_characteristic_modifier' }>,
    context?: {
      occupyHands?: number;
      damageTypeCode?: string | null;
    },
  ): boolean {
    if (effect.min_occupy_hands !== undefined && (context?.occupyHands ?? 0) < effect.min_occupy_hands) {
      return false;
    }
    const types = effect.damage_type_codes;
    if (types && types.length > 0) {
      const damageTypeCode = context?.damageTypeCode;
      if (!damageTypeCode || !types.includes(damageTypeCode)) return false;
    }

    return true;
  }
}
