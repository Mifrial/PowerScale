import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Делит врождённые черты характеристик на модификаторы (±X) и дары (потолок).
 * Коды признаков, не зашитые id: в реальном API id не совпадают с моком.
 */
export class InnateCharacteristicKindService {
  split(
    abilities: EditorAbility[],
    keywords: Keyword[],
    rules: Rule[],
  ): { modifiers: EditorAbility[]; gifts: EditorAbility[] } {
    const idByCode = new Map(keywords.map((keyword) => [keyword.code, keyword.id]));
    const modifierId = idByCode.get('modifier') ?? null;
    const giftId = idByCode.get('gift') ?? null;
    const ruleByCode = new Map(rules.map((rule) => [rule.code, rule]));
    const modifiers: EditorAbility[] = [];
    const gifts: EditorAbility[] = [];
    for (const ability of abilities) {
      if (this.isGift(ability, giftId, ruleByCode)) gifts.push(ability);
      else if (this.isModifier(ability, modifierId, ruleByCode)) modifiers.push(ability);
    }

    return { modifiers, gifts };
  }

  private isGift(ability: EditorAbility, giftId: number | null, ruleByCode: Map<string, Rule>): boolean {
    if (giftId !== null && ability.keywordIds.includes(giftId)) return true;

    return this.grantTypes(ability, ruleByCode).has('characteristic_parameter');
  }

  private isModifier(ability: EditorAbility, modifierId: number | null, ruleByCode: Map<string, Rule>): boolean {
    if (modifierId !== null && ability.keywordIds.includes(modifierId)) return true;

    return this.grantTypes(ability, ruleByCode).has('characteristic_modify');
  }

  private grantTypes(ability: EditorAbility, ruleByCode: Map<string, Rule>): Set<string> {
    const rule = ruleByCode.get(ability.ruleCode);
    if (!rule || rule.type !== 'ability') return new Set();
    const spec = rule.spec as AbilitySpec | undefined;
    if (!spec || spec.type === 'group') return new Set();
    const grants = spec.grants?.flatMap((entry) => entry.grants ?? []) ?? [];

    return new Set(grants.map((grant) => grant.type));
  }
}
