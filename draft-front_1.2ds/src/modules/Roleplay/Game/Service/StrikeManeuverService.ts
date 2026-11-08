import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { LastStrikeSnapshot } from '@/modules/Roleplay/Game/Dto/LastStrikeSnapshot';
import type { ManeuverBoost } from '@/modules/Roleplay/Rule/Dto/Ability/ManeuverBoost';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StrikeManeuver } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeManeuver';
import type { StrikeManeuverEffect } from '@/modules/Roleplay/Rule/Dto/Ability/StrikeManeuver';

/** Манёвр, который можно совершить сразу после удара. */
export interface StrikeManeuverOption {
  ruleCode: string;
  name: string;
  cost: number;
  minSuccess: number;
  targetKey: CombatEntityKey;
  label: string;
}

/**
 * Манёвры после удара: какие карточки актора доступны и как они подписаны.
 *
 * Поведение сверяется с PHP `GameActionManeuver`: клиент только показывает
 * список и отправляет цель прошлого удара, сервер проверяет мастерство,
 * РУ и списывает цену.
 */
export class StrikeManeuverService {
  /** Карточка объявляет манёвр после удара. */
  isManeuver(rule: Rule | null | undefined): boolean {
    return this.maneuverSpec(rule) !== null;
  }

  listAvailable(
    abilities: CharacterAbility[],
    rules: Rule[],
    snapshot: LastStrikeSnapshot | null,
  ): StrikeManeuverOption[] {
    if (!snapshot) return [];
    const options: StrikeManeuverOption[] = [];
    const seen = new Set<string>();
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      if (seen.has(ability.ruleCode)) continue;
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      const spec = this.maneuverSpec(rule);
      if (!rule || !spec) continue;
      const targetKey = this.targetKeyOf(spec, snapshot);
      if (!targetKey) continue;
      seen.add(rule.code);
      options.push({
        ruleCode: rule.code,
        name: rule.name,
        cost: this.costOf(rule),
        minSuccess: spec.min_success,
        targetKey,
        label: this.labelOf(rule, abilities, rules, spec),
      });
    }

    return options;
  }

  /** Цели прошлого удара, на которые можно применить манёвр; null — не манёвр. */
  followUpTargetKeys(rule: Rule | null | undefined, snapshot: LastStrikeSnapshot | null): string[] | null {
    const spec = this.maneuverSpec(rule);
    if (!spec) return null;
    if (!snapshot) return [];

    return snapshot.hits.filter((hit) => hit.attackSr >= spec.min_success).map((hit) => hit.targetKey);
  }

  /** Подпись: цена, порог РУ, мастерство и эффект; ступень усиления справочно. */
  labelOf(
    rule: Rule,
    abilities: CharacterAbility[],
    rules: Rule[],
    spec: StrikeManeuver,
  ): string {
    const base = `${this.costOf(rule)} ОД · ${this.thresholdLabel(spec.min_success, spec.min_weapon_mastery)} → ${this.effectsLabel(spec.effects, rules)}`;
    const boost = this.boostFor(abilities, rules, rule.code);
    if (!boost) return base;

    return `${base}, ${this.thresholdLabel(boost.min_success, boost.min_weapon_mastery)} → ${this.effectsLabel(boost.effects, rules)}`;
  }

  /** Порог РУ и, если задан, минимум мастерства оружия. */
  private thresholdLabel(minSuccess: number, minMastery?: number): string {
    return minMastery ? `${minSuccess}РУ (мастерство ${minMastery})` : `${minSuccess}РУ`;
  }

  private targetKeyOf(spec: StrikeManeuver, snapshot: LastStrikeSnapshot): CombatEntityKey | null {
    const hit = snapshot.hits.find((entry) => entry.attackSr >= spec.min_success);
    const key = hit?.targetKey ?? null;
    if (!key || !/^(character|npc):\d+$/.test(key)) return null;

    return key as CombatEntityKey;
  }

  private boostFor(abilities: CharacterAbility[], rules: Rule[], maneuverCode: string): ManeuverBoost | null {
    let best: ManeuverBoost | null = null;
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.code === ability.ruleCode);
      const boost = this.boostSpec(rule);
      if (!boost || !boost.maneuvers.includes(maneuverCode)) continue;
      if (!best || boost.min_success > best.min_success) best = boost;
    }

    return best;
  }

  private effectsLabel(effects: StrikeManeuverEffect[], rules: Rule[]): string {
    const parts = effects.map((effect) => {
      const state = rules.find((rule) => rule.code === effect.state);

      return `${state?.name ?? effect.state} ${effect.amount}`;
    });

    return parts.join(', ');
  }

  private costOf(rule: Rule): number {
    const spec = rule.spec;
    if (!spec || typeof spec !== 'object' || !('action_components' in spec)) return 0;
    const components = (spec as { action_components?: unknown }).action_components;
    if (!Array.isArray(components)) return 0;
    let cost = 0;
    for (const component of components) {
      if (!component || typeof component !== 'object') continue;
      const row = component as Record<string, unknown>;
      if (row.resource_code === 'action-points' && typeof row.amount === 'number') {
        cost += row.amount;
      }
    }

    return cost;
  }

  private maneuverSpec(rule: Rule | null | undefined): StrikeManeuver | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('strike_maneuver' in rule.spec) || !rule.spec.strike_maneuver) return null;

    return rule.spec.strike_maneuver;
  }

  private boostSpec(rule: Rule | null | undefined): ManeuverBoost | null {
    if (!rule || rule.type !== 'ability' || !rule.spec || typeof rule.spec !== 'object') return null;
    if (!('maneuver_boost' in rule.spec) || !rule.spec.maneuver_boost) return null;

    return rule.spec.maneuver_boost;
  }
}
