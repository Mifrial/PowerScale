import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { actionEffectLabelService } from '@/modules/Roleplay/Rule/init';
import { ADVANTAGE_SOURCE_CIRCUMSTANCES } from '@/modules/Roleplay/Rule/init';

export class ActionEffectService {
  effectsOf(rule: Rule | null | undefined): ActionEffect[] {
    const effects = rule?.spec && 'action_effects' in rule.spec ? rule.spec.action_effects : undefined;

    return effects ? [...effects] : [];
  }

  describe(effect: ActionEffect): string {
    return actionEffectLabelService.describe(effect);
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
  ): number {
    return this.effectsOf(rule)
      .filter(
        (effect): effect is Extract<ActionEffect, { type: 'current_action_attack_characteristic_modifier' }> =>
          effect.type === 'current_action_attack_characteristic_modifier' &&
          effect.scope.components.includes(component) &&
          this.scopeIncludesHit(effect.scope, hitNumber),
      )
      .reduce((total, effect) => total + effect.delta, 0);
  }

  currentActionCheckModifier(rule: Rule | null | undefined, checkCode: string): number {
    return this.effectsOf(rule)
      .filter((effect) => effect.type === 'current_action_check_modifier' && effect.check_codes.includes(checkCode))
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
      .map((effect) => ({ sourceRuleId: rule?.id ?? '', effect }));
  }

  effectsAfterProcess(rule: Rule | null | undefined): PendingActionEffect[] {
    if (!rule || rule.type !== 'ability' || !rule.spec || !('type' in rule.spec) || rule.spec.type !== 'process')
      return [];

    return (rule.spec.process.completion_effects ?? []).map((effect) => ({
      sourceRuleId: rule.id,
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
    targetDexterityMasteryAdjustments: { sourceRuleId: string; delta: number }[];
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
    const targetDexterityMasteryAdjustments: { sourceRuleId: string; delta: number }[] = [];
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
        targetDexterityMasteryAdjustments.push({ sourceRuleId: pending.sourceRuleId, delta: appliedDelta });
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

  private scopeIncludesHit(scope: { hit_count: number | 'all' }, hitNumber: number): boolean {
    return scope.hit_count === 'all' || hitNumber <= scope.hit_count;
  }
}
