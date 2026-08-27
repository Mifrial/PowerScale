import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { ResourceSpec } from '@/modules/Roleplay/Rule/Dto/ResourceSpec';
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import type { SourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/SourceRef';
import type { NamedOption } from '@/modules/Roleplay/Rule/Dto/NamedOption';

export class RuleReferenceService {
  speciesOptions(rules: Rule[], excludeRuleId?: string): NamedOption[] {
    return rules
      .filter((r) => r.type === 'species' && r.id !== excludeRuleId)
      .map((r) => ({ code: r.code, name: r.name }));
  }

  characteristicOptions(rules: Rule[], spaceId: number): CharacteristicRef[] {
    return rules
      .filter(
        (r) =>
          r.type === 'characteristic' && r.spaceId === spaceId && !(r.spec as CharacteristicSpec | undefined)?.formula,
      )
      .map((r) => ({ code: r.code, name: r.name }));
  }

  resourceOptions(rules: Rule[]): ResourceRef[] {
    return rules
      .filter((r) => r.type === 'resource')
      .map((r) => ({
        code: r.code,
        name: r.name,
        isDimensional: !!(r.spec as ResourceSpec | undefined)?.is_dimensional,
      }));
  }

  damageTypeOptions(rules: Rule[]): NamedOption[] {
    return rules.filter((r) => r.type === 'damage_type').map((r) => ({ code: r.code, name: r.name }));
  }

  abilityOptions(rules: Rule[]): AbilityRef[] {
    return rules
      .filter((r) => r.type === 'ability' && !this.isGroupRule(r))
      .map((r) => ({ code: r.code, name: r.name }));
  }

  /** Группирующие правила (type 'group') — контейнеры, на которые ссылаются участники. */
  groupOptions(rules: Rule[]): NamedOption[] {
    return rules
      .filter((r) => r.type === 'ability' && this.isGroupRule(r))
      .map((r) => ({ code: r.code, name: r.name }));
  }

  private isGroupRule(rule: Rule): boolean {
    const spec = rule.spec as { type?: string } | undefined;

    return spec?.type === 'group';
  }

  sourceOptions(rules: Rule[]): SourceRef[] {
    return rules.filter((r) => r.type === 'source').map((r) => ({ code: r.code, name: r.name }));
  }

  senseOptions(rules: Rule[]): NamedOption[] {
    return rules.filter((r) => r.type === 'sense').map((r) => ({ code: r.code, name: r.name }));
  }

  stateOptions(rules: Rule[]): NamedOption[] {
    return rules.filter((r) => r.type === 'state').map((r) => ({ code: r.code, name: r.name }));
  }

  itemOptions(rules: Rule[]): NamedOption[] {
    return rules.filter((r) => r.type === 'item').map((r) => ({ code: r.code, name: r.name }));
  }

  zoneOptions(rules: Rule[]): { label: string; value: string }[] {
    return rules.filter((r) => r.type === 'points').map((r) => ({ label: r.name, value: r.code }));
  }

  abilityNameMap(rules: Rule[]): Map<string, string> {
    const map = new Map<string, string>();
    for (const r of rules) {
      if (r.type === 'ability') map.set(r.code, r.name);
    }

    return map;
  }
}
