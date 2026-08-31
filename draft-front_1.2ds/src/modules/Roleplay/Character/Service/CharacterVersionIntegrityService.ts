import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class CharacterVersionIntegrityService {
  invalidBuildRuleIds(build: CharacterBuild, rules: Rule[]): string[] {
    const referencedRuleIds = new Set<string>();
    if (build.raceRuleCode) referencedRuleIds.add(build.raceRuleCode);
    build.resources.forEach((entry) => referencedRuleIds.add(entry.ruleCode));
    build.abilities.forEach((entry) => referencedRuleIds.add(entry.ruleCode));
    build.inventory.forEach((entry) => {
      if (entry.ruleCode) referencedRuleIds.add(entry.ruleCode);
      entry.modifierRuleCodes?.forEach((ruleCode) => referencedRuleIds.add(ruleCode));
    });
    build.states.forEach((entry) => {
      referencedRuleIds.add(entry.stateRuleCode);
      if (entry.poison?.poisonRuleCode) referencedRuleIds.add(entry.poison.poisonRuleCode);
    });

    return this.missingRuleIds(referencedRuleIds, rules);
  }

  removeUnsupportedFromBuild(build: CharacterBuild, ruleCodes: string[]): CharacterBuild {
    const unsupported = new Set(ruleCodes);

    return {
      ...build,
      raceRuleCode: build.raceRuleCode && unsupported.has(build.raceRuleCode) ? null : build.raceRuleCode,
      resources: build.resources.filter((entry) => !unsupported.has(entry.ruleCode)),
      abilities: build.abilities.filter((entry) => !unsupported.has(entry.ruleCode)),
      inventory: build.inventory
        .filter((entry) => !entry.ruleCode || !unsupported.has(entry.ruleCode))
        .map((entry) => ({
          ...entry,
          modifierRuleCodes: entry.modifierRuleCodes?.filter((ruleCode) => !unsupported.has(ruleCode)),
        })),
      states: build.states
        .filter((entry) => !unsupported.has(entry.stateRuleCode))
        .map((entry) => ({
          ...entry,
          poison:
            entry.poison && entry.poison.poisonRuleCode && unsupported.has(entry.poison.poisonRuleCode)
              ? { ...entry.poison, poisonRuleCode: null }
              : entry.poison,
        })),
    };
  }

  invalidRuleIds(version: CharacterVersion, rules: Rule[]): string[] {
    const referencedRuleIds = new Set<string>();
    if (version.raceRuleCode) referencedRuleIds.add(version.raceRuleCode);
    version.characteristics.forEach((entry) => {
      referencedRuleIds.add(entry.ruleCode);
      entry.modifiers.forEach((modifier) => {
        if (modifier.sourceRuleCode) referencedRuleIds.add(modifier.sourceRuleCode);
      });
    });
    version.resources.forEach((entry) => {
      referencedRuleIds.add(entry.ruleCode);
      entry.bonuses.forEach((bonus) => {
        if (bonus.sourceRuleCode) referencedRuleIds.add(bonus.sourceRuleCode);
      });
    });
    version.abilities.forEach((entry) => referencedRuleIds.add(entry.ruleCode));
    version.inventory.forEach((entry) => {
      if (entry.ruleCode) referencedRuleIds.add(entry.ruleCode);
      entry.modifierRuleCodes?.forEach((ruleCode) => referencedRuleIds.add(ruleCode));
    });
    version.states.forEach((entry) => {
      referencedRuleIds.add(entry.stateRuleCode);
      if (entry.poison?.poisonRuleCode) referencedRuleIds.add(entry.poison.poisonRuleCode);
    });
    version.senses.forEach((entry) => {
      referencedRuleIds.add(entry.ruleCode);
      entry.modifiers.forEach((modifier) => {
        if (modifier.sourceRuleCode) referencedRuleIds.add(modifier.sourceRuleCode);
      });
    });

    return this.missingRuleIds(referencedRuleIds, rules);
  }

  assertValid(version: CharacterVersion, rules: Rule[]): void {
    const invalidRuleIds = this.invalidRuleIds(version, rules);
    if (invalidRuleIds.length > 0) {
      throw new Error(`Версия содержит правила, отсутствующие в ревизии: ${invalidRuleIds.join(', ')}`);
    }
  }

  private missingRuleIds(referencedRuleIds: Set<string>, rules: Rule[]): string[] {
    const knownRuleIds = new Set(rules.map((rule) => rule.code));

    return [...referencedRuleIds].filter((ruleCode) => !knownRuleIds.has(ruleCode)).sort();
  }
}
