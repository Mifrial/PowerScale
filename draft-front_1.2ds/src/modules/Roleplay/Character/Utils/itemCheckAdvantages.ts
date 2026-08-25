import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { ItemCheckAdvantage } from '@/modules/Roleplay/Rule/Dto/Item/ItemCheckAdvantage';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CheckAdvantageQuery } from '@/modules/Roleplay/Character/Utils/stateRuntimeEffects';
import { itemModifierService } from '@/modules/Roleplay/Rule/Service/Instance/itemModifierService';
import { aggregateSourceDeltas } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';

function matchesQuery(effect: ItemCheckAdvantage, query: CheckAdvantageQuery | undefined): boolean {
  const codes = effect.characteristic_codes ?? [];
  const unscoped = !effect.includes_hit && codes.length === 0;
  if (unscoped) return true;
  if (!query) return false;
  if (query.kind === 'hit') return Boolean(effect.includes_hit);

  return codes.includes(query.code);
}

/** Помехи экипированных предметов на проверку: источник — предмет, не производные характеристик. */
export function checkAdvantageModifiersFromItems(
  version: CharacterVersion | null | undefined,
  rules: Rule[],
  query?: CheckAdvantageQuery,
): AdvantageModifier[] {
  if (!version) return [];
  const entries: AdvantageModifier[] = [];
  for (const item of version.inventory) {
    if (!item.equipped || item.ruleId === null) continue;
    const rule = rules.find((entry) => entry.id === item.ruleId);
    if (!rule || rule.type !== 'item') continue;
    const modifiers = (item.modifierRuleIds ?? [])
      .map((id) => rules.find((entry) => entry.id === id))
      .filter((entry): entry is Rule => entry != null);
    const spec = itemModifierService.applyStack(rule.spec as ItemSpec, modifiers, []).spec;
    for (const effect of spec.check_advantages ?? []) {
      if (effect.delta === 0 || !matchesQuery(effect, query)) continue;
      entries.push({
        source_code: item.ruleId,
        source_label: rule.name,
        delta: effect.delta,
      });
    }
  }

  return aggregateSourceDeltas(entries);
}
