import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { EditorMagicPathView } from '@/modules/Roleplay/Character/Dto/Editor/EditorMagicPathView';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { magicStudyUnlockService } from '@/modules/Roleplay/Character/Service/Instance/magicStudyUnlockService';

type MagicStudyGrant = Extract<Grant, { type: 'magic_study' }>;

/**
 * Сводка открытых путей волшебства: потолки изучения по путям и опыт пути.
 */
export class EditorMagicPathViewsService {
  constructor(private readonly unlocks = magicStudyUnlockService) {}

  build(
    build: CharacterBuild,
    rules: Rule[],
    keywords: Keyword[] = [],
    snapshot?: CharacterSnapshot,
    characteristicValues?: Map<string, DimensionalNumberValue>,
  ): EditorMagicPathView[] {
    const studyUnlocks = this.unlocks.unlocksOf(build.abilities, rules);
    const granted = this.unlocks.grantedPathCodes(build.abilities, rules);
    const pathCodes = this.pathCodes(granted, studyUnlocks, rules);
    const keywordCodeById = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));

    return pathCodes
      .map((pathCode) => {
        const rule = rules.find((entry) => entry.code === pathCode && entry.type === 'magic_path');
        if (!rule) return null;
        const limits = this.limitsOf(pathCode, studyUnlocks, rules, snapshot, characteristicValues);

        return {
          pathCode,
          pathName: rule.name,
          experience: this.unlocks.pathExperience(build.abilities, rules, pathCode, keywordCodeById),
          restrictionLabels: limits.map((maxCost) => this.restrictionLabel(maxCost)),
        };
      })
      .filter((entry): entry is EditorMagicPathView => entry !== null);
  }

  private pathCodes(granted: readonly string[], unlocks: readonly MagicStudyGrant[], rules: Rule[]): string[] {
    const codes: string[] = [...granted];
    const seen = new Set(granted);
    const add = (code: string): void => {
      if (!code || seen.has(code)) return;
      seen.add(code);
      codes.push(code);
    };
    for (const grant of unlocks) {
      const paths = grant.paths ?? (grant.path_code ? [grant.path_code] : []);
      if (paths === 'any') {
        for (const rule of rules) {
          if (rule.type === 'magic_path') add(rule.code);
        }
        continue;
      }

      for (const owner of paths) {
        for (const code of this.unlocks.coveredPathCodes(rules, owner)) add(code);
      }
    }

    return codes;
  }

  private limitsOf(
    pathCode: string,
    unlocks: readonly MagicStudyGrant[],
    rules: Rule[],
    snapshot?: CharacterSnapshot,
    characteristicValues?: Map<string, DimensionalNumberValue>,
  ): number[] {
    const limits: number[] = [];
    for (const grant of unlocks) {
      if (!this.grantCovers(grant, pathCode, rules)) continue;
      const maxCost =
        characteristicValues === undefined
          ? this.unlocks.resolvedMaxCost(grant, snapshot)
          : this.unlocks.resolvedMaxCostFromCharacteristics(grant, characteristicValues);
      if (!limits.includes(maxCost)) limits.push(maxCost);
    }

    return limits;
  }

  private grantCovers(grant: MagicStudyGrant, pathCode: string, rules: Rule[]): boolean {
    const paths = grant.paths ?? (grant.path_code ? [grant.path_code] : []);
    if (paths === 'any') return true;

    return paths.some((owner) => this.unlocks.coveredPathCodes(rules, owner).has(pathCode));
  }

  private restrictionLabel(maxCost: number): string {
    return `Изучение карточек с базовой стоимостью до ${maxCost}`;
  }
}
