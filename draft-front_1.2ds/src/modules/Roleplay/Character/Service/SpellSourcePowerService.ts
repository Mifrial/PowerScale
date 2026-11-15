import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { keywordExperienceService } from '@/modules/Roleplay/Character/Service/Instance/keywordExperienceService';
import { formulaEvaluationService } from '@/modules/Roleplay/Character/Service/Instance/formulaEvaluationService';

export interface SpellSourcePowerInput {
  rules: Rule[];
  keywords: Keyword[];
  abilities: CharacterAbility[];
  /** Эффективные уровни способностей (включая производные). */
  levels: Map<string, number>;
  /** Живые значения характеристик для формул `amount`. */
  characteristicValues: Map<string, DimensionalNumberValue>;
  inventory: InventoryItem[];
  /** Ключ источника (`inventory:<id>`). */
  sourceKey: string;
}

/**
 * Вклад `spell_source_power` в мощь источника.
 *
 * Один и тот же расчёт для редактора (предпросмотр листа) и диалога каста.
 */
export class SpellSourcePowerService {
  /** Эффективные уровни: выбранные и производные по опыту признака. */
  effectiveLevels(abilities: CharacterAbility[], rules: Rule[], keywords: Keyword[]): Map<string, number> {
    const levels = new Map<string, number>();
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      levels.set(ability.ruleCode, Math.max(levels.get(ability.ruleCode) ?? 0, ability.level));
    }
    for (const rule of rules) {
      const spec = rule.spec as AbilitySpecBase | undefined;
      const derived = spec?.derived_level;
      if (!derived) continue;
      const experience = keywordExperienceService.experienceOf(
        derived.source_keyword,
        abilities,
        rules,
        keywords,
        (ability, _rule, abilitySpec) => keywordExperienceService.zoneLadderCost(ability, abilitySpec),
      );
      const level = derived.thresholds.filter((threshold) => experience >= threshold).length;
      if (level > (levels.get(rule.code) ?? 0)) levels.set(rule.code, level);
    }

    return levels;
  }

  /** Вклад каждой подходящей способности: код → бонус. */
  contributions(input: SpellSourcePowerInput): Record<string, number> {
    const [kind, raw = ''] = input.sourceKey.split(':', 2);
    const inventoryId = kind === 'inventory' ? Number(raw) : 0;
    const item = inventoryId > 0 ? input.inventory.find((row) => row.id === inventoryId) : undefined;
    const itemRule = item?.ruleCode ? input.rules.find((entry) => entry.code === item.ruleCode) : null;
    const itemSpec = itemRule?.spec as ItemSpec | undefined;
    const chosen = new Map(input.abilities.map((ability) => [ability.ruleCode, ability]));
    const context = {
      abilityLevels: input.levels,
      characteristicValues: input.characteristicValues,
    };

    const result: Record<string, number> = {};
    for (const [code, level] of input.levels) {
      if (level < 1) continue;
      const rule = input.rules.find((entry) => entry.code === code);
      if (!rule) continue;
      const spec = rule.spec as AbilitySpecBase | undefined;
      const block = spec?.spell_source_power;
      if (!block) continue;
      const ability = chosen.get(code) ?? null;
      if (!this.scopeMatches(block, ability, itemRule?.code ?? null, itemSpec, inventoryId)) continue;
      const amount = formulaEvaluationService.evaluateScalar(block.amount, context);
      if (amount !== 0) result[code] = (result[code] ?? 0) + amount;
    }

    return result;
  }

  /** Сумма вклада `spell_source_power`. */
  bonus(input: SpellSourcePowerInput): number {
    return Object.values(this.contributions(input)).reduce((sum, value) => sum + value, 0);
  }

  /** Область действия бонуса. */
  private scopeMatches(
    block: NonNullable<AbilitySpecBase['spell_source_power']>,
    ability: { targets?: Record<string, number> } | null,
    itemCode: string | null,
    itemSpec: ItemSpec | undefined,
    inventoryId: number,
  ): boolean {
    switch (block.scope.kind) {
      case 'any':
        return true;
      case 'innate':
        return itemSpec?.innate === true;
      case 'item':
        return itemCode !== null && itemCode === block.scope.item_code;
      case 'source':
        return inventoryId > 0 && (ability?.targets?.[block.scope.target_code] ?? 0) === inventoryId;
      default:
        return false;
    }
  }
}

export const spellSourcePowerService = new SpellSourcePowerService();
