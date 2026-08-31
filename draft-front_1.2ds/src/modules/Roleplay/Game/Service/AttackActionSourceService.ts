import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import {
  asActionAbilitySpec,
  asProcessAbilitySpec,
  actionOdCost,
  findRuleByRef,
} from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { ATTACK_KEYWORD_IDS } from '@/modules/Roleplay/Game/Constant/Combat/ATTACK_KEYWORD_IDS';

export class AttackActionSourceService {
  list(rules: Rule[], overview: CharacterOverview | null): CombatActionOption[] {
    const ownedRuleIds = new Set(overview?.abilities.map((ability) => ability.ruleCode) ?? []);

    return rules.flatMap((rule) => {
      const actionSpec = asActionAbilitySpec(rule);
      const processSpec = asProcessAbilitySpec(rule);
      const isAttack = (rule.keywordIds ?? []).includes(ATTACK_KEYWORD_IDS.attack);
      if (!isAttack || (!actionSpec && !processSpec)) return [];
      if (!ownedRuleIds.has(rule.code) && !this.isAutomatic(actionSpec?.zones)) return [];

      return [
        {
          ruleCode: rule.code,
          code: rule.code,
          name: rule.name,
          odCost: actionSpec ? actionOdCost(actionSpec.action_components) : 0,
          effects: actionEffectService.effectsOf(rule),
          isAttack: true,
          isProcess: processSpec !== null,
          process: processSpec ?? undefined,
          attackMode: actionSpec?.attack_mode,
        },
      ];
    });
  }

  compatibleProfiles(rule: Rule | null, attacks: AttackOverview[]): AttackOverview[] {
    if (!rule) return attacks;
    const keywords = new Set(rule.keywordIds ?? []);
    const melee = keywords.has(ATTACK_KEYWORD_IDS.melee) || keywords.has(ATTACK_KEYWORD_IDS.processMelee);
    const ranged = keywords.has(ATTACK_KEYWORD_IDS.ranged) || keywords.has(ATTACK_KEYWORD_IDS.processRanged);
    if (!melee && !ranged) return attacks;

    return attacks.filter(
      (attack) => (melee && attack.profileType === 'strike') || (ranged && attack.profileType !== 'strike'),
    );
  }

  isProfileAvailable(profile: AttackOverview | null, availableProfiles: AttackOverview[]): boolean {
    if (!profile) return false;

    return availableProfiles.some(
      (candidate) =>
        candidate.itemRuleCode === profile.itemRuleCode &&
        candidate.profileType === profile.profileType &&
        (candidate.profileIndex ?? 0) === (profile.profileIndex ?? 0),
    );
  }

  sameItemRef(left: string, right: string, rules: Rule[]): boolean {
    if (left === right) return true;
    const leftRule = findRuleByRef(rules, left);
    const rightRule = findRuleByRef(rules, right);

    return Boolean(leftRule && rightRule && leftRule.code === rightRule.code);
  }

  favoriteAttack(
    attacks: AttackOverview[],
    favorite: { itemRuleCode: string; profileType: AttackOverview['profileType']; profileIndex: number } | null,
    rules: Rule[],
  ): AttackOverview | null {
    if (!favorite) return null;

    return (
      attacks.find(
        (attack) =>
          this.sameItemRef(attack.itemRuleCode, favorite.itemRuleCode, rules) &&
          attack.profileType === favorite.profileType &&
          (attack.profileIndex ?? 0) === favorite.profileIndex,
      ) ?? null
    );
  }

  maxTargets(rule: Rule | null): number {
    const actionSpec = rule ? asActionAbilitySpec(rule) : null;

    return actionSpec?.attack_mode === 'wide' ? (actionSpec.max_targets ?? 1) : 1;
  }

  validateTargetCount(rule: Rule | null, targetKeys: string[]): string | null {
    const maxTargets = this.maxTargets(rule);
    if (targetKeys.length <= maxTargets) return null;

    return `Атака может иметь не более ${maxTargets} целей`;
  }

  private isAutomatic(zones: Record<string, unknown> | undefined): boolean {
    return Object.values(zones ?? {}).some(
      (zone) => typeof zone === 'object' && zone !== null && 'kind' in zone && zone.kind === 'automatic',
    );
  }
}
