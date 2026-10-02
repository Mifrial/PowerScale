import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import { DEFAULT_ATTACK_AP } from '@/modules/Roleplay/Game/Constant/Combat/DEFAULT_ATTACK_AP';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import { ATTACK_KEYWORD_IDS } from '@/modules/Roleplay/Game/Constant/Combat/ATTACK_KEYWORD_IDS';

import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { ActionOperation } from '@/modules/Roleplay/Rule/Dto/Ability/ActionOperation';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';

export const SIMPLE_MELEE_ATTACK_CODE = 'simple-melee-attack';
export const SIMPLE_RANGED_ATTACK_CODE = 'simple-ranged-attack';

export type CombatActionRole = 'dodge' | 'block' | 'turn' | 'wait' | 'recover-stability' | 'simple-touch';

export interface CombatActionOption {
  ruleCode: string;
  code: string;
  name: string;
  odCost: number;
  isVariableCost?: boolean;
  effects?: ActionEffect[];
  isAttack?: boolean;
  isReaction?: boolean;
  isProcess?: boolean;
  process?: ProcessSpec;
  operations?: ActionOperation[];
  attackMode?: 'single' | 'wide';
  combatAction?: CombatActionRole;
}

export function asActionAbilitySpec(rule: Rule | null | undefined): Extract<AbilitySpec, { type: 'action' }> | null {
  if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object' || !('type' in rule.spec)) {
    return null;
  }
  if (rule.spec.type !== 'action') return null;

  return rule.spec;
}

export function asProcessAbilitySpec(
  rule: Rule | null | undefined,
): Extract<AbilitySpec, { type: 'process' }>['process'] | null {
  if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object' || !('type' in rule.spec)) {
    return null;
  }
  if (rule.spec.type !== 'process') return null;

  return rule.spec.process;
}

export interface ResourceCost {
  resourceCode: string;
  amount: number;
}

export function turnResourceCode(rules: Rule[]): string {
  return (
    rules.find((candidate) => {
      if (candidate.type !== 'resource') return false;
      const spec = candidate.spec as ResourceSpec | undefined;

      return spec?.auto_add === true;
    })?.code ?? ''
  );
}

export function resourceCosts(
  components: ActionComponent[] | undefined,
  chosenAmount = 0,
  resourceCode = '',
): ResourceCost[] {
  if (!components) return [];
  const totals = new Map<string, number>();
  for (const component of components) {
    if (component.type !== 'resource') continue;
    let amount = 0;
    if (typeof component.amount === 'object' && 'type' in component.amount) {
      if (component.amount.type === 'chosen' && resourceCode && component.resource_code === resourceCode) {
        amount = chosenAmount;
      }
    } else {
      amount = typeof component.amount === 'number' ? component.amount : component.amount.base;
    }
    totals.set(component.resource_code, (totals.get(component.resource_code) ?? 0) + amount);
  }

  return [...totals.entries()].map(([code, amount]) => ({ resourceCode: code, amount }));
}

export function actionOdCost(
  components: ActionComponent[] | undefined,
  chosenAmount = 0,
  resourceCode = '',
): number {
  if (!resourceCode) return 0;

  return resourceCosts(components, chosenAmount, resourceCode).find((cost) => cost.resourceCode === resourceCode)
    ?.amount ?? 0;
}

export function actionUsesChosenCost(components: ActionComponent[] | undefined): boolean {
  return (
    components?.some(
      (component) =>
        component.type === 'resource' &&
        typeof component.amount === 'object' &&
        'type' in component.amount &&
        component.amount.type === 'chosen',
    ) ?? false
  );
}

export function isAutomaticAbility(spec: Pick<AbilitySpecBase, 'zones'>): boolean {
  return Object.values(spec.zones).some((zone) => zone?.kind === 'automatic');
}

function hasKeyword(rule: Rule, keywordId: number): boolean {
  return (rule.keywordIds ?? []).includes(keywordId);
}

/** Ссылка на правило в бою: semantic code или ещё лежащий storage id. */
export function findRuleByRef(rules: Rule[], ref: string | null | undefined): Rule | undefined {
  if (!ref) return undefined;

  return rules.find((entry) => entry.code === ref);
}

export function actionRefEquals(option: CombatActionOption, ref: string | null | undefined, rules: Rule[]): boolean {
  if (!ref) return false;
  if (option.code === ref || option.ruleCode === ref) return true;
  const rule = findRuleByRef(rules, ref);

  return Boolean(rule && (rule.code === option.code || rule.code === option.ruleCode));
}

function optionFromAttackRule(
  rule: Rule,
  spec: NonNullable<ReturnType<typeof asActionAbilitySpec>>,
  resourceCode: string,
): CombatActionOption {
  return {
    ruleCode: rule.code,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec.action_components, 0, resourceCode) || DEFAULT_ATTACK_AP,
    effects: actionEffectService.effectsOf(rule),
    operations: spec.operations,
    attackMode: spec.attack_mode,
    isAttack: hasKeyword(rule, ATTACK_KEYWORD_IDS.attack),
  };
}

export function listAttackActions(
  rules: Rule[],
  overview: CharacterOverview | null,
  profileType: 'strike' | 'throw' | 'shoot',
): CombatActionOption[] {
  const owned = new Set(overview?.abilities.map((ability) => ability.ruleCode) ?? []);
  const options: CombatActionOption[] = [];
  for (const rule of rules) {
    const spec = asActionAbilitySpec(rule);
    if (!spec) continue;
    const isAttack = hasKeyword(rule, ATTACK_KEYWORD_IDS.attack);
    if (!isAttack) continue;
    if (!isAutomaticAbility(spec) && !owned.has(rule.code)) continue;
    const melee = hasKeyword(rule, ATTACK_KEYWORD_IDS.melee);
    const ranged = hasKeyword(rule, ATTACK_KEYWORD_IDS.ranged);
    if (profileType === 'strike' && ranged && !melee) continue;
    if ((profileType === 'throw' || profileType === 'shoot') && melee && !ranged) continue;
    options.push(optionFromAttackRule(rule, spec, turnResourceCode(rules)));
  }

  return options;
}

export function attackActionById(rules: Rule[], ruleCode: string | null | undefined): CombatActionOption | null {
  const rule = findRuleByRef(rules, ruleCode);
  if (!rule) return null;
  const spec = asActionAbilitySpec(rule);
  if (!spec) return null;

  return optionFromAttackRule(rule, spec, turnResourceCode(rules));
}

export function combatActionRule(rules: Rule[], role: CombatActionRole): Rule | undefined {
  return rules.find((entry) => asActionAbilitySpec(entry)?.combat_action === role);
}

export function reactionAction(rules: Rule[], reaction: HitDefenseReaction | null): CombatActionOption | null {
  if (reaction !== 'dodge' && reaction !== 'block') return null;
  const rule = combatActionRule(rules, reaction);
  if (!rule) return null;
  const spec = asActionAbilitySpec(rule);

  return {
    ruleCode: rule.code,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec?.action_components, 0, turnResourceCode(rules)),
    effects: [],
    isAttack: true,
    combatAction: reaction,
  };
}

export function reactionOdCost(reaction: HitDefenseReaction | null, rules: Rule[]): number {
  if (!reaction || reaction === 'ignore') return 0;

  return reactionAction(rules, reaction)?.odCost ?? 0;
}

export function turnAction(rules: Rule[]): CombatActionOption {
  const rule = combatActionRule(rules, 'turn');
  if (!rule) {
    return { ruleCode: '', code: '', name: '', odCost: 0, effects: [], isAttack: true };
  }
  const spec = asActionAbilitySpec(rule);

  return {
    ruleCode: rule.code,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec?.action_components, 0, turnResourceCode(rules)),
    effects: [],
    isAttack: true,
    combatAction: 'turn',
  };
}

export function defenseOdCost(reaction: HitDefenseReaction | null, turned: boolean, rules: Rule[]): number {
  return reactionOdCost(reaction, rules) + (turned && reaction !== 'ignore' && reaction ? turnAction(rules).odCost : 0);
}

export function defaultTouchAction(
  rules: Rule[],
  overview: CharacterOverview | null,
): CombatActionOption | null {
  return (
    listAttackActions(rules, overview, 'strike').find((action) => {
      const rule = findRuleByRef(rules, action.code);

      return asActionAbilitySpec(rule)?.combat_action === 'simple-touch';
    }) ?? null
  );
}
