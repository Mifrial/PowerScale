import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { BLOOD_CLOTTING_RULE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/BLOOD_CLOTTING_RULE_CODE';

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
        combatSectionByCode.get(rule.code) ??
        rule.catalogSection ??
        this.sectionForRule(rule, speciesByCode, keywordCodeById, combatSectionByCode),
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
    if (rule.type === 'source') return 'basic-sources';
    if (rule.type === 'sense') return 'basic-senses';
    if (rule.type === 'characteristic') {
      if (rule.code === 'magic-power' || rule.code === 'magic-control' || rule.code === 'spirituality') {
        return 'magic-rules-characteristics';
      }

      return 'basic-characteristics';
    }
    if (rule.type === 'check') return 'basic-checks';
    if (rule.type === 'magic_path') return 'magic-rules-paths';
    if (rule.type === 'resource' || rule.type === 'points') return 'basic-resources';
    if (rule.type === 'damage_type') return rule.code === 'arcane' ? 'magic-rules-damage-types' : 'scenes-damage-types';
    if (rule.type === 'state') {
      return rule.code === 'shock' || rule.code === 'electrocharge' ? 'magic-rules-states' : 'scenes-states';
    }
    if (rule.type === 'poison') return 'scenes-poisons';
    if (rule.type === 'language' || rule.type === 'script' || rule.type === 'ethnicity') {
      return 'abilities-acquired-mental-intellect';
    }
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
    const spec = rule.spec as { type?: string; parent_ability_code?: string | null } | undefined;
    if (rule.code === 'dodge' || rule.code === 'block' || rule.code === 'turn') return 'scenes-combat-defense';
    if (rule.code === 'simple-melee-attack' || rule.code === 'simple-ranged-attack' || rule.code === 'simple-touch' || rule.code === 'simple-push') {
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
    if (keywords.has('electromancy')) return 'abilities-acquired-magic-spells-electromancy';
    if (spec?.type === 'spell' && keywords.has('psionic')) return 'abilities-acquired-magic-spells-psionic';
    if (spec?.type === 'spell') return 'abilities-acquired-magic-spells-electromancy';
    if (keywords.has('shaman') || rule.code === 'otherworldly-contact') {
      return 'abilities-acquired-magic-paths-shaman';
    }
    if (keywords.has('psionic') || rule.code === 'psionic-awakening' || rule.code === 'psionic-control') {
      return 'abilities-acquired-magic-paths-psionic';
    }
    if (keywords.has('magic-path') || keywords.has('arcanist') || rule.code === 'becoming-arcanist') {
      return 'abilities-acquired-magic-paths-arcanist';
    }
    if (rule.code === 'magic-core-capacity') return 'abilities-acquired-magic-sources-core';
    if (rule.code === 'magic-resistance') return 'abilities-innate-magic-individual';
    if (rule.code === 'careful-magic') return 'abilities-acquired-magic-common';
    if (keywords.has('innate') && keywords.has('characteristic') && spec?.type === 'trait') {
      return 'abilities-innate-characteristics';
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
    if (rule.code === 'spell-sustaining') return 'magic-rules-casting';
    if (rule.code === 'common-traits-surcharge') return 'abilities-innate-common';
    if (rule.code === BLOOD_CLOTTING_RULE_CODE) return 'scenes-other';

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
