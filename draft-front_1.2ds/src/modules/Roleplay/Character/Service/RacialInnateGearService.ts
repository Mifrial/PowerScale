import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { RacialInnateGearSheet } from '@/modules/Roleplay/Character/Dto/RacialInnateGearSheet';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { SpeciesSpec } from '@/modules/Roleplay/Rule/Dto/Race/SpeciesSpec';
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

const WEAPON_PROFICIENCY_CODE = 'vladenie-oruzhiem';

export class RacialInnateGearService {
  constructor(private readonly races = raceSpecService) {}
  /** Item grants from automatic racial abilities: later (race) overrides earlier (species). */
  racialItemGrants(raceRuleId: string | null, rules: Rule[]): Map<string, number> {
    const result = new Map<string, number>();
    for (const ref of this.racialAutomaticRefs(raceRuleId, rules)) {
      for (const grant of this.itemGrantsOf(ref.ability_code, rules)) {
        result.set(grant.item_code, grant.quantity ?? 1);
      }
    }

    return result;
  }

  /** Item codes that any species/race automatic ability grants — managed by reconcile. */
  managedRacialItemCodes(rules: Rule[]): Set<string> {
    const codes = new Set<string>();
    for (const rule of rules) {
      if (rule.type !== 'race' && rule.type !== 'species') continue;
      const spec = rule.spec as { abilities?: RaceAbilityRef[] } | undefined;
      for (const ref of spec?.abilities ?? []) {
        if (!ref.automatic) continue;
        for (const grant of this.itemGrantsOf(ref.ability_code, rules)) {
          codes.add(grant.item_code);
        }
      }
    }

    return codes;
  }

  /**
   * Материализует врождённые предметы и бесплатное владение 1 с расовых grant item.
   * Мутация (предмет не из расового automatic-гранта) не трогается.
   */
  applyRacialInnateGear<T extends RacialInnateGearSheet>(sheet: T, rules: Rule[]): T {
    const grants = this.racialItemGrants(sheet.raceRuleId, rules);
    const managed = this.managedRacialItemCodes(rules);
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const byId = new Map(rules.map((rule) => [rule.id, rule]));

    let inventory = sheet.inventory.map((item) => ({ ...item }));
    inventory = inventory.filter((item) => {
      if (item.ruleId === null) return true;
      const rule = byId.get(item.ruleId);
      const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
      if (!rule || !spec?.innate) return true;
      if (!managed.has(rule.code)) return true;

      return grants.has(rule.code);
    });

    let nextId = inventory.reduce((max, item) => Math.max(max, item.id), 0);
    for (const [itemCode, quantity] of grants) {
      const rule = byCode.get(itemCode);
      const spec = rule?.type === 'item' ? (rule.spec as ItemSpec | undefined) : undefined;
      if (!rule || !spec?.innate) continue;
      const existing = inventory.find((item) => item.ruleId === rule.id);
      if (existing) {
        existing.quantity = quantity;
        existing.equipped = true;
        continue;
      }
      nextId += 1;
      inventory.push({ id: nextId, ruleId: rule.id, quantity, equipped: true, modifierRuleIds: [] });
    }

    const proficiencyRule = rules.find((rule) => rule.code === WEAPON_PROFICIENCY_CODE);
    let abilities = sheet.abilities.map((ability) => ({ ...ability }));
    const grantedFamilies = new Set<string>();
    if (proficiencyRule) {
      for (const [itemCode] of grants) {
        const itemRule = byCode.get(itemCode);
        const spec = itemRule?.type === 'item' ? (itemRule.spec as ItemSpec | undefined) : undefined;
        const familyCode = spec?.proficiency_family_code;
        if (!familyCode) continue;
        const family = byCode.get(familyCode);
        if (!family) continue;
        grantedFamilies.add(family.code);
        const existing = abilities.find((ability) => this.isProficiencyOf(ability, proficiencyRule.id, family));
        if (!existing) {
          abilities.push({
            ruleId: proficiencyRule.id,
            level: 1,
            domain: family.name,
            domainCode: family.code,
            gifted: true,
            zone: 'or',
          });
          continue;
        }
        existing.gifted = true;
        if (existing.level < 1) existing.level = 1;
      }

      abilities = abilities.filter((ability) => {
        if (ability.ruleId !== proficiencyRule.id || !ability.gifted) return true;
        const familyCode = ability.domainCode ?? ability.domain;
        if (!familyCode) return true;
        const family = rules.find(
          (rule) => rule.type === 'weapon_family' && (rule.code === familyCode || rule.name === familyCode),
        );
        if (!family) return true;
        if (grantedFamilies.has(family.code)) return true;
        if (ability.level > 1) {
          ability.gifted = false;

          return true;
        }

        return false;
      });
    }

    return { ...sheet, inventory, abilities };
  }

  private racialAutomaticRefs(raceRuleId: string | null, rules: Rule[]): RaceAbilityRef[] {
    if (!raceRuleId) return [];
    const raceRule = rules.find((rule) => rule.id === raceRuleId);
    if (!raceRule) return [];
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    if (raceRule.type !== 'race' && raceRule.type !== 'species') return [];
    const spec = raceRule.spec as RaceSpec | SpeciesSpec | undefined;
    const parentCode = spec?.parent_race_code ?? null;
    const own = spec?.abilities ?? [];
    const inherited = this.races.collectInheritedAbilities(parentCode, byCode);
    // Дальний вид → ближний вид → раса: последний item_code побеждает.
    const ordered: RaceAbilityRef[] = [...inherited].reverse();
    ordered.push(...own);

    return ordered.filter((ref) => ref.automatic);
  }

  private itemGrantsOf(abilityCode: string, rules: Rule[]): Extract<Grant, { type: 'item' }>[] {
    const rule = rules.find((entry) => entry.code === abilityCode);
    const spec = rule?.type === 'ability' ? (rule.spec as AbilitySpec | undefined) : undefined;
    if (!spec || spec.type === 'group') return [];
    const result: Extract<Grant, { type: 'item' }>[] = [];
    for (const entry of spec.grants ?? []) {
      for (const grant of entry.grants) {
        if (grant.type === 'item' && grant.item_code) result.push(grant);
      }
    }

    return result;
  }

  private isProficiencyOf(ability: CharacterAbility, proficiencyRuleId: string, family: Rule): boolean {
    if (ability.ruleId !== proficiencyRuleId) return false;
    const key = ability.domainCode ?? ability.domain;

    return key === family.code || key === family.name;
  }
}
