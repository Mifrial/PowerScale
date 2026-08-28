import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { DEFAULT_ATTACK_AP } from '@/modules/Roleplay/Game/Constant/Combat/DEFAULT_ATTACK_AP';
import { ATTACK_KEYWORD_IDS } from '@/modules/Roleplay/Game/Constant/Combat/ATTACK_KEYWORD_IDS';

import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { ProcessSpec } from '@/modules/Roleplay/Rule/Dto/Ability/ProcessSpec';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';

export const SIMPLE_MELEE_ATTACK_CODE = 'simple-melee-attack';
export const SIMPLE_RANGED_ATTACK_CODE = 'simple-ranged-attack';
export const DODGE_CODE = 'dodge';
export const BLOCK_CODE = 'block';
export const TURN_CODE = 'turn';
export const WAIT_ACTION_CODE = 'wait';

export interface CombatActionOption {
  ruleId: string;
  code: string;
  name: string;
  odCost: number;
  isVariableCost?: boolean;
  effects?: ActionEffect[];
  isAttack?: boolean;
  isReaction?: boolean;
  isProcess?: boolean;
  process?: ProcessSpec;
}

export function asActionAbilitySpec(rule: Rule): Extract<AbilitySpec, { type: 'action' }> | null {
  if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object' || !('type' in rule.spec)) return null;
  if (rule.spec.type !== 'action') return null;

  return rule.spec;
}

export function asProcessAbilitySpec(rule: Rule): Extract<AbilitySpec, { type: 'process' }>['process'] | null {
  if (rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object' || !('type' in rule.spec)) return null;
  if (rule.spec.type !== 'process') return null;

  return rule.spec.process;
}

export function actionOdCost(components: ActionComponent[] | undefined): number {
  if (!components) return 0;
  let total = 0;
  for (const component of components) {
    if (component.type !== 'resource' || component.resource_code !== ACTION_POINTS_CODE) continue;
    if (typeof component.amount === 'object' && 'type' in component.amount) continue;
    total += typeof component.amount === 'number' ? component.amount : component.amount.base;
  }

  return total;
}

export function actionUsesChosenCost(components: ActionComponent[] | undefined): boolean {
  return components?.some(
    (component) =>
      component.type === 'resource' &&
      typeof component.amount === 'object' &&
      'type' in component.amount &&
      component.amount.type === 'chosen',
  ) ?? false;
}

export function isAutomaticAbility(spec: Pick<AbilitySpecBase, 'zones'>): boolean {
  return Object.values(spec.zones).some((zone) => zone?.kind === 'automatic');
}

function hasKeyword(rule: Rule, keywordId: number): boolean {
  return (rule.keywordIds ?? []).includes(keywordId);
}

export function listAttackActions(
  rules: Rule[],
  overview: CharacterOverview | null,
  profileType: 'strike' | 'throw' | 'shoot',
): CombatActionOption[] {
  const owned = new Set(overview?.abilities.map((ability) => ability.ruleId) ?? []);
  const options: CombatActionOption[] = [];
  for (const rule of rules) {
    const spec = asActionAbilitySpec(rule);
    if (!spec) continue;
    const isAttack = hasKeyword(rule, ATTACK_KEYWORD_IDS.attack);
    if (!isAttack) continue;
    if (!isAutomaticAbility(spec) && !owned.has(rule.id)) continue;
    const melee = hasKeyword(rule, ATTACK_KEYWORD_IDS.melee);
    const ranged = hasKeyword(rule, ATTACK_KEYWORD_IDS.ranged);
    if (profileType === 'strike' && ranged && !melee) continue;
    if ((profileType === 'throw' || profileType === 'shoot') && melee && !ranged) continue;
    options.push({
      ruleId: rule.id,
      code: rule.code,
      name: rule.name,
      odCost: actionOdCost(spec.action_components) || DEFAULT_ATTACK_AP,
      effects: actionEffectService.effectsOf(rule),
      isAttack: true,
    });
  }

  return options;
}

export function attackActionById(rules: Rule[], ruleId: string | null | undefined): CombatActionOption | null {
  if (!ruleId) return null;
  const rule = rules.find((entry) => entry.id === ruleId);
  if (!rule) return null;
  const spec = asActionAbilitySpec(rule);
  if (!spec) return null;

  return {
    ruleId: rule.id,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec.action_components) || DEFAULT_ATTACK_AP,
    effects: actionEffectService.effectsOf(rule),
    isAttack: hasKeyword(rule, ATTACK_KEYWORD_IDS.attack),
  };
}

export function reactionAction(rules: Rule[], reaction: HitDefenseReaction | null): CombatActionOption | null {
  if (reaction !== 'dodge' && reaction !== 'block') return null;
  const code = reaction === 'dodge' ? DODGE_CODE : BLOCK_CODE;
  const rule = rules.find((entry) => entry.code === code && entry.type === 'ability');
  if (!rule) {
    return {
      ruleId: '',
      code,
      name: reaction === 'dodge' ? 'Уклонение' : 'Блок',
      odCost: reaction === 'dodge' ? 1 : 2,
      effects: [],
      isAttack: true,
    };
  }
  const spec = asActionAbilitySpec(rule);

  return {
    ruleId: rule.id,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec?.action_components) || (reaction === 'dodge' ? 1 : 2),
    effects: [],
    isAttack: true,
  };
}

export function reactionOdCost(reaction: HitDefenseReaction | null, rules: Rule[]): number {
  if (!reaction || reaction === 'ignore') return 0;

  return reactionAction(rules, reaction)?.odCost ?? 0;
}

export function turnAction(rules: Rule[]): CombatActionOption {
  const rule = rules.find((entry) => entry.code === TURN_CODE && entry.type === 'ability');
  if (!rule) {
    return { ruleId: '', code: TURN_CODE, name: 'Поворот', odCost: 1, effects: [], isAttack: true };
  }
  const spec = asActionAbilitySpec(rule);

  return {
    ruleId: rule.id,
    code: rule.code,
    name: rule.name,
    odCost: actionOdCost(spec?.action_components) || 1,
    effects: [],
    isAttack: true,
  };
}

export function defenseOdCost(reaction: HitDefenseReaction | null, turned: boolean, rules: Rule[]): number {
  return reactionOdCost(reaction, rules) + (turned && reaction !== 'ignore' && reaction ? turnAction(rules).odCost : 0);
}
