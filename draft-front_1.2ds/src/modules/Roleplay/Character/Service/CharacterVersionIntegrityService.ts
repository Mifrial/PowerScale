import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export class CharacterVersionIntegrityService {
  invalidBuildRuleIds(build: CharacterBuild, rules: Rule[]): string[] {
    const referencedRuleIds = new Set<string>();
    if (build.raceRuleId) referencedRuleIds.add(build.raceRuleId);
    build.resources.forEach((entry) => referencedRuleIds.add(entry.ruleId));
    build.abilities.forEach((entry) => referencedRuleIds.add(entry.ruleId));
    build.inventory.forEach((entry) => {
      if (entry.ruleId) referencedRuleIds.add(entry.ruleId);
      entry.modifierRuleIds?.forEach((ruleId) => referencedRuleIds.add(ruleId));
    });
    build.states.forEach((entry) => {
      referencedRuleIds.add(entry.stateRuleId);
      if (entry.poison?.poisonRuleId) referencedRuleIds.add(entry.poison.poisonRuleId);
    });

    return this.missingRuleIds(referencedRuleIds, rules);
  }

  removeUnsupportedFromBuild(build: CharacterBuild, ruleIds: string[]): CharacterBuild {
    const unsupported = new Set(ruleIds);

    return {
      ...build,
      raceRuleId: build.raceRuleId && unsupported.has(build.raceRuleId) ? null : build.raceRuleId,
      resources: build.resources.filter((entry) => !unsupported.has(entry.ruleId)),
      abilities: build.abilities.filter((entry) => !unsupported.has(entry.ruleId)),
      inventory: build.inventory
        .filter((entry) => !entry.ruleId || !unsupported.has(entry.ruleId))
        .map((entry) => ({
          ...entry,
          modifierRuleIds: entry.modifierRuleIds?.filter((ruleId) => !unsupported.has(ruleId)),
        })),
      states: build.states
        .filter((entry) => !unsupported.has(entry.stateRuleId))
        .map((entry) => ({
          ...entry,
          poison:
            entry.poison && entry.poison.poisonRuleId && unsupported.has(entry.poison.poisonRuleId)
              ? { ...entry.poison, poisonRuleId: null }
              : entry.poison,
        })),
    };
  }

  invalidRuleIds(version: CharacterVersion, rules: Rule[]): string[] {
    const referencedRuleIds = new Set<string>();
    if (version.raceRuleId) referencedRuleIds.add(version.raceRuleId);
    version.characteristics.forEach((entry) => {
      referencedRuleIds.add(entry.ruleId);
      entry.modifiers.forEach((modifier) => {
        if (modifier.sourceRuleId) referencedRuleIds.add(modifier.sourceRuleId);
      });
    });
    version.resources.forEach((entry) => {
      referencedRuleIds.add(entry.ruleId);
      entry.bonuses.forEach((bonus) => {
        if (bonus.sourceRuleId) referencedRuleIds.add(bonus.sourceRuleId);
      });
    });
    version.abilities.forEach((entry) => referencedRuleIds.add(entry.ruleId));
    version.inventory.forEach((entry) => {
      if (entry.ruleId) referencedRuleIds.add(entry.ruleId);
      entry.modifierRuleIds?.forEach((ruleId) => referencedRuleIds.add(ruleId));
    });
    version.states.forEach((entry) => {
      referencedRuleIds.add(entry.stateRuleId);
      if (entry.poison?.poisonRuleId) referencedRuleIds.add(entry.poison.poisonRuleId);
    });
    version.senses.forEach((entry) => {
      referencedRuleIds.add(entry.ruleId);
      entry.modifiers.forEach((modifier) => {
        if (modifier.sourceRuleId) referencedRuleIds.add(modifier.sourceRuleId);
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
    const knownRuleIds = new Set(rules.map((rule) => rule.id));

    return [...referencedRuleIds].filter((ruleId) => !knownRuleIds.has(ruleId)).sort();
  }
}
