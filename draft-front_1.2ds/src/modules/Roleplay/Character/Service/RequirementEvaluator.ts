import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/init';

/**
 * Оценивает требования способностей (Requirement) против снимка персонажа.
 * Список требований = неявное И; явная логика — рекурсивные группы and/or.
 * domainContext — домен экземпляра множественного навыка: has_ability-требования тогда ищут
 * экземпляр с тем же доменом («Письменность того же языка»), а не максимальный уровень.
 */
export class RequirementEvaluator {
  /** Все требования списка выполняются (неявное И). */
  evaluateAll(requirements: Requirement[], snapshot: CharacterSnapshot, domainContext?: string): boolean {
    return requirements.every((requirement) => this.evaluate(requirement, snapshot, domainContext));
  }

  evaluate(requirement: Requirement, snapshot: CharacterSnapshot, domainContext?: string): boolean {
    return this.failureReason(requirement, snapshot, domainContext) === null;
  }

  /** Первая невыполненная причина в человекочитаемом виде (null — все выполнены). */
  firstFailure(requirements: Requirement[], snapshot: CharacterSnapshot, domainContext?: string): string | null {
    for (const requirement of requirements) {
      const reason = this.failureReason(requirement, snapshot, domainContext);
      if (reason !== null) return reason;
    }

    return null;
  }

  private failureReason(requirement: Requirement, snapshot: CharacterSnapshot, domainContext?: string): string | null {
    switch (requirement.type) {
      case 'has_ability': {
        const minLevel = requirement.min_level ?? 1;
        const label = `«${this.abilityName(requirement.ability_code, snapshot)}» уровня ${minLevel}`;
        if (domainContext !== undefined) {
          const instances = snapshot.abilityInstances?.get(requirement.ability_code) ?? [];
          if (instances.some((instance) => instance.domain === domainContext && instance.level >= minLevel)) {
            return null;
          }

          return `требуется способность ${label} (${domainContext})`;
        }
        const level = snapshot.abilityLevels.get(requirement.ability_code) ?? 0;
        if (level >= minLevel) return null;

        return `требуется способность ${label}`;
      }
      case 'has_ability_keyword': {
        let count = 0;
        for (const [code, level] of snapshot.abilityLevels) {
          if (level > 0 && snapshot.abilityKeywords.get(code)?.has(requirement.keyword_code)) count++;
        }
        if (count >= requirement.min_count) return null;

        return `требуется признак «${this.keywordName(requirement.keyword_code, snapshot)}» на ${requirement.min_count} способностях`;
      }
      case 'has_keyword': {
        if (snapshot.keywordCodes.has(requirement.keyword_code)) return null;

        return `требуется признак «${this.keywordName(requirement.keyword_code, snapshot)}»`;
      }
      case 'min_weapon_mastery': {
        const families = this.familiesWithKeyword(requirement.keyword_code, snapshot);
        const met = Array.from(families.entries()).some(([, level]) => level >= requirement.min_level);

        if (met) return null;

        return `требуется владение оружием с тэгом «${this.keywordName(requirement.keyword_code, snapshot)}» уровня ${requirement.min_level}`;
      }
      case 'characteristic_value': {
        const value = snapshot.characteristicValues.get(requirement.characteristic_code);
        // Сравнение по значению (base+size), а не по toNumber: «4↑» (5↓-подобные) не путать с «4».
        const met =
          value !== undefined && CharacteristicNumber.from(value).compare(new DimensionalNumber(requirement.min)) >= 0;

        if (met) return null;

        return `требуется характеристика «${this.characteristicName(requirement.characteristic_code, snapshot)}» от ${new DimensionalNumber(requirement.min).toString()}`;
      }
      case 'resource_limit': {
        const limit = snapshot.resourceLimits.get(requirement.resource_code);
        if (limit === undefined) return `требуется ресурс «${this.resourceName(requirement.resource_code, snapshot)}»`;
        if (requirement.min === undefined) return null;
        if (this.toNumber(limit) < this.toNumber(requirement.min)) {
          return `требуется лимит ресурса «${this.resourceName(requirement.resource_code, snapshot)}» от ${this.labelOf(requirement.min)}`;
        }

        return null;
      }
      case 'current_speed': {
        const component = snapshot.currentSpeed?.[requirement.axis];
        const met =
          component !== undefined &&
          component.direction === requirement.direction &&
          component.stepsPerActionPoint >= requirement.min_steps_per_action_point;
        if (met) return null;

        return `требуется скорость ${requirement.direction} не менее ${requirement.min_steps_per_action_point} шагов/ОД`;
      }
      case 'and':
        return this.firstFailure(requirement.children, snapshot, domainContext);
      case 'or': {
        if (
          requirement.children.length > 0 &&
          requirement.children.some((child) => this.evaluate(child, snapshot, domainContext))
        ) {
          return null;
        }

        if (requirement.children.every((child) => child.type === 'has_ability')) {
          const names = requirement.children
            .map((child) => this.abilityName((child as { ability_code: string }).ability_code, snapshot))
            .join('», «');

          return `нужна одна из способностей: «${names}»`;
        }

        const alternatives = requirement.children
          .map((child) => this.failureReason(child, snapshot, domainContext))
          .filter((reason): reason is string => reason !== null);

        return alternatives.length > 0 ? alternatives.join(' или ') : 'ни одно из условий не выполнено';
      }
    }
  }

  private abilityName(code: string, snapshot: CharacterSnapshot): string {
    return snapshot.abilityNames?.get(code) ?? code;
  }

  private keywordName(code: string, snapshot: CharacterSnapshot): string {
    return snapshot.keywordNames?.get(code) ?? code;
  }

  private characteristicName(code: string, snapshot: CharacterSnapshot): string {
    return snapshot.characteristicNames?.get(code) ?? code;
  }

  private resourceName(code: string, snapshot: CharacterSnapshot): string {
    return snapshot.resourceNames?.get(code) ?? code;
  }

  /** Все семьи оружия с указанным тэгом и их уровни владения. */
  private familiesWithKeyword(keywordCode: string, snapshot: CharacterSnapshot): Map<string, number> {
    const proficiency = snapshot.weaponProficiencyLevels ?? new Map();
    const familyTags = snapshot.weaponFamilyTags ?? new Map();
    const result = new Map<string, number>();

    const targetFamilies = familyTags.get(keywordCode);

    if (!targetFamilies) return result;

    for (const [familyCode, level] of proficiency) {
      if (targetFamilies.has(familyCode)) {
        result.set(familyCode, level);
      }
    }

    return result;
  }

  private toNumber(value: number | DimensionalNumberValue): number {
    return typeof value === 'number' ? value : new DimensionalNumber(value).toNumber();
  }

  private labelOf(value: number | DimensionalNumberValue): string {
    return typeof value === 'number' ? String(value) : new DimensionalNumber(value).toString();
  }
}
