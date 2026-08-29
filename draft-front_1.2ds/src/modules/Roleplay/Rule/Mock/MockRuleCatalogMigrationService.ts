import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class MockRuleCatalogMigrationService {
  migrateRules(
    rules: Rule[],
    keywordCodeById: ReadonlyMap<number, string>,
    combatSectionByCode: ReadonlyMap<string, string> = new Map(),
  ): Rule[] {
    const speciesByCode = new Map(rules.filter((rule) => rule.type === 'species').map((rule) => [rule.code, rule]));

    return rules.map((rule, index) => ({
      ...rule,
      catalogSection:
        rule.catalogSection ?? this.sectionForRule(rule, speciesByCode, keywordCodeById, combatSectionByCode),
      catalogSortOrder: rule.catalogSortOrder ?? (index + 1) * 100,
    }));
  }

  private sectionForRule(
    rule: Rule,
    speciesByCode: ReadonlyMap<string, Rule>,
    keywordCodeById: ReadonlyMap<number, string>,
    combatSectionByCode: ReadonlyMap<string, string>,
  ): string {
    if (rule.type === 'species') return `species-${rule.code}`;
    if (rule.type === 'race') {
      const parentCode = this.specParentCode(rule);

      return parentCode && speciesByCode.has(parentCode) ? `species-${parentCode}` : 'races';
    }
    if (rule.type === 'characteristic' || rule.type === 'sense') return 'basic-characteristics';
    if (rule.type === 'check') return 'basic-checks';
    if (rule.type === 'resource' || rule.type === 'points') return 'basic-resources';
    if (rule.type === 'damage_type') return 'scenes-damage-types';
    if (rule.type === 'state') return 'scenes-states';
    if (rule.type === 'poison') return 'scenes-poisons';
    if (rule.type === 'language') return 'abilities-acquired-mental-intellect';
    if (rule.type === 'age') return 'races';
    if (rule.type === 'weapon_family') return 'items-equipment-weapons-melee';
    if (rule.type === 'item_modifier' || rule.type === 'item_modifier_type') return 'items-other';
    if (rule.type === 'item') return this.itemSection(rule, keywordCodeById);
    if (rule.type === 'ability') return this.abilitySection(rule, keywordCodeById, combatSectionByCode);
    if (rule.type === 'simple') return this.simpleRuleSection(rule);

    return 'scenes-other';
  }

  private itemSection(rule: Rule, keywordCodeById: ReadonlyMap<number, string>): string {
    const spec = rule.spec as { weapon?: unknown; shield?: unknown; armor?: unknown } | undefined;
    const keywords = this.keywordCodes(rule, keywordCodeById);
    if (spec?.weapon) {
      return keywords.has('item-section-ranged') ? 'items-equipment-weapons-ranged' : 'items-equipment-weapons-melee';
    }
    if (spec?.shield) return 'items-equipment-shields';
    if (spec?.armor) return 'items-equipment-armor';
    if (keywords.has('potion') || keywords.has('item-section-potion')) return 'items-consumables-potions';
    if (keywords.has('crystal') || keywords.has('item-section-crystal')) {
      return 'items-consumables-magic-crystals';
    }

    return 'items-other';
  }

  private abilitySection(
    rule: Rule,
    keywordCodeById: ReadonlyMap<number, string>,
    combatSectionByCode: ReadonlyMap<string, string>,
  ): string {
    const spec = rule.spec as { type?: string } | undefined;
    if (rule.code === 'dodge' || rule.code === 'block' || rule.code === 'turn') return 'scenes-combat-defense';
    if (rule.code === 'simple-melee-attack' || rule.code === 'simple-ranged-attack') {
      return 'scenes-combat-basic-attacks';
    }
    if (this.isWeaponSkill(rule)) return 'abilities-acquired-melee-weapon-skills';
    const keywords = this.keywordCodes(rule, keywordCodeById);
    if (keywords.has('medicine')) return 'abilities-acquired-medicine';
    const explicitCombatSection = combatSectionByCode.get(rule.code);
    if (explicitCombatSection) return explicitCombatSection;
    if (spec?.type === 'action') {
      if (keywords.has('attack')) return 'scenes-combat-basic-attacks';
      if (keywords.has('reaction')) return 'scenes-combat-defense';

      return 'scenes-other';
    }
    if (spec?.type === 'trait') return 'abilities-innate-common';
    if (keywords.has('method-perception')) return 'abilities-acquired-mental-perception';
    if (keywords.has('method-intellect')) return 'abilities-acquired-mental-intellect';
    if (keywords.has('section-willpower')) return 'abilities-acquired-mental-will';
    if (keywords.has('section-medicine')) return 'abilities-acquired-medicine';
    if (keywords.has('section-body')) return 'abilities-acquired-physical';
    if (keywords.has('section-social')) return 'abilities-acquired-social';
    if (keywords.has('section-melee')) return 'abilities-acquired-melee-combat';
    if (keywords.has('section-ranged')) return 'abilities-acquired-ranged';

    return 'abilities-acquired-other';
  }

  private isWeaponSkill(rule: Rule): boolean {
    const spec = rule.spec as { requirements?: { requirements?: { type?: string }[] }[] } | undefined;

    return (
      spec?.requirements?.some((level) =>
        level.requirements?.some((requirement) => requirement.type === 'min_weapon_mastery'),
      ) ?? false
    );
  }

  private simpleRuleSection(rule: Rule): string {
    if (rule.code === 'strike-procedure' || rule.code === 'throw-procedure' || rule.code === 'shoot-procedure') {
      return 'scenes-combat-procedures';
    }
    if (rule.code === 'flanking-attack') return 'scenes-combat-tactics';

    return 'scenes-other';
  }

  private keywordCodes(rule: Rule, keywordCodeById: ReadonlyMap<number, string>): Set<string> {
    return new Set(
      (rule.keywordIds ?? []).map((id) => keywordCodeById.get(id)).filter((code): code is string => !!code),
    );
  }

  private specParentCode(rule: Rule): string | null {
    const spec = rule.spec as { parent_race_code?: string | null } | undefined;

    return spec?.parent_race_code ?? null;
  }
}
