import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';

type SkillStudyGrant = Extract<Grant, { type: 'skill_study' }>;

/** Бесплатный слот книжного червя: знание или письменность, не речь. */
export class SkillStudyUnlockService {
  unlocksOf(abilities: readonly CharacterAbility[], rules: Rule[]): SkillStudyGrant[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const unlocks: SkillStudyGrant[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'skill_study') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          unlocks.push(grant);
        }
      }
    }

    return unlocks;
  }

  paidRungsFree(
    ability: CharacterAbility,
    unlocks: readonly SkillStudyGrant[],
    occupants: readonly CharacterAbility[],
  ): number {
    if (unlocks.length === 0) return 0;
    const matching = unlocks.filter((grant) => grant.ability_codes.includes(ability.ruleCode));
    if (matching.length === 0) return 0;
    const grant = matching[0];
    const slot = grant.max_instances ?? 1;
    const used = occupants.filter((entry) => grant.ability_codes.includes(entry.ruleCode) && entry.level >= 1);
    const index = used.findIndex(
      (entry) =>
        entry.ruleCode === ability.ruleCode &&
        (entry.domainCode ?? null) === (ability.domainCode ?? null) &&
        (entry.domain ?? '') === (ability.domain ?? ''),
    );
    if (index < 0 || index >= slot) return 0;

    return grant.max_level;
  }
}
