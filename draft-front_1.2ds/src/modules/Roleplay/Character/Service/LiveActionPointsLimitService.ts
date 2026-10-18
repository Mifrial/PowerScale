import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { ResourceLimitOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceLimitOverview';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';

export class LiveActionPointsLimitService {
  private readonly formula = new FormulaEvaluationService();

  /**
   * Лимит авто-ресурса (ОД): base спеки + формулы от текущих характеристик + дары,
   * не являющиеся adjustments, + эффекты состояний. Floor 0.
   */
  liveAutoResourceLimit(
    resource: ResourceValue,
    version: CharacterVersion,
    rules: Rule[],
    characteristicValues: Map<string, DimensionalNumberValue>,
    resourceCode: string,
  ): number {
    const rule = rules.find((item) => item.code === resource.ruleCode);
    const spec = rule?.spec as ResourceSpec | undefined;
    if (!spec?.auto_add || !spec.limit) return Math.max(0, this.storedResourceLimit(resource));

    const context = this.formulaContext(version, rules, characteristicValues);
    let delta = 0;
    const adjustmentSourceIds = new Set<string | null>();
    for (const adjustment of spec.limit.adjustments) {
      delta += this.formula.evaluate(adjustment.value, context);
      const source = rules.find((item) => item.code === adjustment.source_code);
      if (source) adjustmentSourceIds.add(source.code);
    }
    for (const bonus of resource.bonuses) {
      if (adjustmentSourceIds.has(bonus.sourceRuleCode)) continue;
      delta += bonus.delta;
    }
    const rawBase = spec.limit.base;
    const base = typeof rawBase === 'number' ? rawBase : rawBase.base;
    const effects = stateRuntimeEffectsService.accumulateStateEffects(
      version.states.filter((state) => !resource.bonuses.some((bonus) => bonus.sourceRuleCode === state.stateRuleCode)),
      rules,
    );
    delta += effects.resourceLimitModify.get(resourceCode) ?? 0;
    let limit = Math.max(0, base + delta);
    const set = effects.resourceLimitSet.get(resourceCode);
    if (set !== undefined) limit = Math.max(0, set);

    return limit;
  }

  liveActionPointsLimit(
    version: CharacterVersion,
    rules: Rule[],
    characteristicValues: Map<string, DimensionalNumberValue>,
  ): number | null {
    const rule = rules.find((item) => {
      if (item.type !== 'resource') return false;
      const spec = item.spec as ResourceSpec | undefined;

      return spec?.auto_add === true;
    });
    if (!rule) return null;
    const resource = version.resources.find((item) => item.ruleCode === rule.code);
    if (!resource) return null;

    return this.liveAutoResourceLimit(resource, version, rules, characteristicValues, rule.code);
  }

  /** Лимит и те бонусы, из которых он собран. */
  liveAutoResourceView(
    version: CharacterVersion,
    rules: Rule[],
    characteristicValues: Map<string, DimensionalNumberValue>,
    resource: ResourceValue,
  ): { limit: number; bonuses: ResourceLimitOverview[] } | null {
    const rule = rules.find((item) => item.code === resource.ruleCode);
    const spec = rule?.spec as ResourceSpec | undefined;
    if (!spec?.auto_add || !spec.limit) return null;

    const context = this.formulaContext(version, rules, characteristicValues);
    const bonuses: ResourceLimitOverview[] = [];
    let delta = 0;
    const adjustmentSourceIds = new Set<string | null>();
    for (const adjustment of spec.limit.adjustments) {
      const amount = this.formula.evaluate(adjustment.value, context);
      delta += amount;
      const source = rules.find((item) => item.code === adjustment.source_code);
      if (source) adjustmentSourceIds.add(source.code);
      if (amount !== 0) {
        bonuses.push({
          source: source?.name ?? adjustment.source_code ?? '',
          sourceRuleCode: source?.code ?? null,
          sourceHref: null,
          delta: amount,
        });
      }
    }
    for (const bonus of resource.bonuses) {
      if (adjustmentSourceIds.has(bonus.sourceRuleCode) || bonus.delta === 0) continue;
      delta += bonus.delta;
      const source = bonus.sourceRuleCode === null ? null : rules.find((item) => item.code === bonus.sourceRuleCode);
      bonuses.push({
        source: source?.name ?? bonus.sourceLabel ?? bonus.sourceRuleCode ?? '',
        sourceRuleCode: bonus.sourceRuleCode,
        sourceHref: null,
        delta: bonus.delta,
      });
    }
    const rawBase = spec.limit.base;
    const base = typeof rawBase === 'number' ? rawBase : rawBase.base;
    const effects = stateRuntimeEffectsService.accumulateStateEffects(
      version.states.filter((state) => !resource.bonuses.some((bonus) => bonus.sourceRuleCode === state.stateRuleCode)),
      rules,
    );
    const stateDelta = effects.resourceLimitModify.get(rule.code) ?? 0;
    if (stateDelta !== 0) {
      delta += stateDelta;
      bonuses.push({ source: 'От состояния', sourceRuleCode: null, sourceHref: null, delta: stateDelta });
    }
    let limit = Math.max(0, base + delta);
    const set = effects.resourceLimitSet.get(rule.code);
    if (set !== undefined) limit = Math.max(0, set);

    return { limit, bonuses };
  }

  private storedResourceLimit(resource: ResourceValue): number {
    return resource.base.base + resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);
  }

  private abilityLevelsOf(version: CharacterVersion, rules: Rule[]): Map<string, number> {
    const levels = new Map<string, number>();
    for (const ability of version.abilities) {
      const rule = rules.find((item) => item.code === ability.ruleCode);
      if (!rule) continue;
      const current = levels.get(rule.code) ?? 0;
      if (ability.level > current) levels.set(rule.code, ability.level);
    }

    return levels;
  }

  private formulaContext(
    version: CharacterVersion,
    rules: Rule[],
    characteristicValues: Map<string, DimensionalNumberValue>,
  ): FormulaContext {
    return { characteristicValues, abilityLevels: this.abilityLevelsOf(version, rules) };
  }
}
