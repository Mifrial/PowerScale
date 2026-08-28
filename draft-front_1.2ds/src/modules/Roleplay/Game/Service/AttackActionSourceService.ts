import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { asActionAbilitySpec, asProcessAbilitySpec, actionOdCost } from '@/modules/Roleplay/Game/Utils/combatActions';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { ATTACK_KEYWORD_IDS } from '@/modules/Roleplay/Game/Constant/Combat/ATTACK_KEYWORD_IDS';

export class AttackActionSourceService {
  list(rules: Rule[], overview: CharacterOverview | null): CombatActionOption[] {
    const ownedRuleIds = new Set(overview?.abilities.map((ability) => ability.ruleId) ?? []);

    return rules.flatMap((rule) => {
      const actionSpec = asActionAbilitySpec(rule);
      const processSpec = asProcessAbilitySpec(rule);
      const isAttack = (rule.keywordIds ?? []).includes(ATTACK_KEYWORD_IDS.attack);
      if (!isAttack || (!actionSpec && !processSpec)) return [];
      if (!ownedRuleIds.has(rule.id) && !this.isAutomatic(actionSpec?.zones)) return [];

      return [
        {
          ruleId: rule.id,
          code: rule.code,
          name: rule.name,
          odCost: actionSpec ? actionOdCost(actionSpec.action_components) : 0,
          effects: actionEffectService.effectsOf(rule),
          isAttack: true,
          isProcess: processSpec !== null,
          process: processSpec ?? undefined,
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

  private isAutomatic(zones: Record<string, unknown> | undefined): boolean {
    return Object.values(zones ?? {}).some(
      (zone) => typeof zone === 'object' && zone !== null && 'kind' in zone && zone.kind === 'automatic',
    );
  }
}
