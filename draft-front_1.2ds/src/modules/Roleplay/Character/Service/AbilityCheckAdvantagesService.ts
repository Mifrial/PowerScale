import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CheckAdvantageQuery } from '@/modules/Roleplay/Character/Dto/CheckAdvantageQuery';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';

export class AbilityCheckAdvantagesService {
  checkAdvantageModifiersFromAbilities(
    version: CharacterVersion | null | undefined,
    rules: Rule[],
    query?: CheckAdvantageQuery,
  ): AdvantageModifier[] {
    if (!version || query?.kind !== 'check') return [];
    const entries: AdvantageModifier[] = [];
    for (const ability of version.abilities) {
      if (ability.level < 1) continue;
      const rule = rules.find((entry) => entry.id === ability.ruleId);
      if (!rule || rule.type !== 'ability') continue;
      const spec = rule.spec as AbilitySpec | undefined;
      if (!spec || spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'check_advantage') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          if (!grant.check_codes.includes(query.code) || grant.amount === 0) continue;
          entries.push({
            source_code: grant.source_code ?? rule.code,
            source_label: rule.name,
            delta: grant.amount,
          });
        }
      }
    }

    return aggregateSourceDeltasService.aggregateSourceDeltas(entries);
  }
}
