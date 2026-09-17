import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { PISMENNOST_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/PISMENNOST_ABILITY_CODE';

/**
 * Оценивает требования способностей (Requirement) против снимка персонажа.
 * Список требований = неявное И; явная логика — рекурсивные группы and/or.
 * domainContext — домен экземпляра множественного навыка: has_ability-требования тогда ищут
 * экземпляр с тем же доменом. Письменность: экземпляр script из script_codes языка, не «тот же код языка».
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
    return this.failureReasons(requirements, snapshot, domainContext)[0] ?? null;
  }

  /** Все невыполненные причины списка (неявное И), через «; ». */
  failureSummary(requirements: Requirement[], snapshot: CharacterSnapshot, domainContext?: string): string | null {
    const reasons = this.failureReasons(requirements, snapshot, domainContext);

    return reasons.length === 0 ? null : reasons.join('; ');
  }

  private failureReasons(requirements: Requirement[], snapshot: CharacterSnapshot, domainContext?: string): string[] {
    const reasons: string[] = [];
    for (const requirement of requirements) {
      if (requirement.type === 'and') {
        reasons.push(...this.failureReasons(requirement.children, snapshot, domainContext));
        continue;
      }
      const reason = this.failureReason(requirement, snapshot, domainContext);
      if (reason !== null) reasons.push(reason);
    }

    return reasons;
  }

  private failureReason(requirement: Requirement, snapshot: CharacterSnapshot, domainContext?: string): string | null {
    switch (requirement.type) {
      case 'has_ability': {
        const minLevel = requirement.min_level ?? 1;
        const label = `«${this.abilityName(requirement.ability_code, snapshot)}» уровня ${minLevel}`;
        if (domainContext !== undefined) {
          const instances = snapshot.abilityInstances?.get(requirement.ability_code) ?? [];
          if (
            instances.some((instance) =>
              this.instanceMeetsDomain(instance, domainContext, snapshot, minLevel, requirement.ability_code),
            )
          ) {
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
      case 'has_magic_path': {
        if (snapshot.magicPaths?.has(requirement.path_code)) return null;

        return `требуется путь волшебства «${this.magicPathName(requirement.path_code, snapshot)}»`;
      }
      case 'magic_path_experience': {
        const experience = snapshot.magicPathExperience?.get(requirement.path_code) ?? 0;
        if (experience >= requirement.min) return null;

        return `требуется опыт пути «${this.magicPathName(requirement.path_code, snapshot)}» от ${requirement.min}`;
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
        return this.failureSummary(requirement.children, snapshot, domainContext);
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

  private magicPathName(code: string, snapshot: CharacterSnapshot): string {
    return snapshot.magicPathNames?.get(code) ?? snapshot.keywordNames?.get(code) ?? code;
  }

  /** Экземпляр покрывает домен: то же имя или путь, который контекст включает (шаман ← псионик). */
  private instanceMeetsDomain(
    instance: { domain: string; domainCode: string | null; level: number },
    domainContext: string,
    snapshot: CharacterSnapshot,
    minLevel: number,
    abilityCode: string,
  ): boolean {
    if (instance.level < minLevel) return false;
    if (abilityCode === PISMENNOST_ABILITY_CODE) {
      const scripts = snapshot.languageScripts?.get(domainContext);
      const scriptCode = instance.domainCode ?? instance.domain;

      return scripts != null && scripts.has(scriptCode);
    }
    if (instance.domain === domainContext || instance.domainCode === domainContext) return true;
    const ownerPath = this.pathCodeOfDomain(domainContext, snapshot);
    const learnedPath = instance.domainCode;
    if (!ownerPath || !learnedPath) return false;

    return snapshot.magicPathCovers?.get(ownerPath)?.has(learnedPath) ?? false;
  }

  private pathCodeOfDomain(domainContext: string, snapshot: CharacterSnapshot): string | null {
    if (snapshot.magicPathNames?.has(domainContext)) return domainContext;
    for (const [code, name] of snapshot.magicPathNames ?? []) {
      if (name === domainContext) return code;
    }

    return null;
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
