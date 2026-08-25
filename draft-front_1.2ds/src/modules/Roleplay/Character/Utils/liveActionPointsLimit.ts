import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import { accumulateStateEffects } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';

function storedResourceLimit(resource: ResourceValue): number {
  return resource.base.base + resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0);
}

export const ACTION_POINTS_RESOURCE_CODE = 'action-points';

const formula = new FormulaEvaluationService();

function abilityLevelsOf(version: CharacterVersion, rules: Rule[]): Map<string, number> {
  const levels = new Map<string, number>();
  for (const ability of version.abilities) {
    const rule = rules.find((item) => item.id === ability.ruleId);
    if (!rule) continue;
    const current = levels.get(rule.code) ?? 0;
    if (ability.level > current) levels.set(rule.code, ability.level);
  }

  return levels;
}

function formulaContext(
  version: CharacterVersion,
  rules: Rule[],
  characteristicValues: Map<string, DimensionalNumberValue>,
): FormulaContext {
  return { characteristicValues, abilityLevels: abilityLevelsOf(version, rules) };
}

/**
 * Лимит авто-ресурса (ОД): base спеки + формулы от текущих характеристик + дары,
 * не являющиеся adjustments, + эффекты состояний. Floor 0.
 */
export function liveAutoResourceLimit(
  resource: ResourceValue,
  version: CharacterVersion,
  rules: Rule[],
  characteristicValues: Map<string, DimensionalNumberValue>,
  resourceCode: string,
): number {
  const rule = rules.find((item) => item.id === resource.ruleId);
  const spec = rule?.spec as ResourceSpec | undefined;
  if (!spec?.auto_add || !spec.limit) return Math.max(0, storedResourceLimit(resource));

  const context = formulaContext(version, rules, characteristicValues);
  let delta = 0;
  const adjustmentSourceIds = new Set<string | null>();
  for (const adjustment of spec.limit.adjustments) {
    delta += formula.evaluate(adjustment.value, context);
    const source = rules.find((item) => item.code === adjustment.source_code);
    if (source) adjustmentSourceIds.add(source.id);
  }
  for (const bonus of resource.bonuses) {
    if (adjustmentSourceIds.has(bonus.sourceRuleId)) continue;
    delta += bonus.delta;
  }
  const rawBase = spec.limit.base;
  const base = typeof rawBase === 'number' ? rawBase : rawBase.base;
  const effects = accumulateStateEffects(version.states, rules);
  delta += effects.resourceLimitModify.get(resourceCode) ?? 0;
  let limit = Math.max(0, base + delta);
  const set = effects.resourceLimitSet.get(resourceCode);
  if (set !== undefined) limit = Math.max(0, set);

  return limit;
}

export function liveActionPointsLimit(
  version: CharacterVersion,
  rules: Rule[],
  characteristicValues: Map<string, DimensionalNumberValue>,
): number | null {
  const rule = rules.find((item) => item.code === ACTION_POINTS_RESOURCE_CODE && item.type === 'resource');
  if (!rule) return null;
  const resource = version.resources.find((item) => item.ruleId === rule.id);
  if (!resource) return null;

  return liveAutoResourceLimit(resource, version, rules, characteristicValues, ACTION_POINTS_RESOURCE_CODE);
}
