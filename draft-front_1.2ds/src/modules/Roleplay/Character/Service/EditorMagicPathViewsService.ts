import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { EditorMagicPathView } from '@/modules/Roleplay/Character/Dto/Editor/EditorMagicPathView';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { magicStudyUnlockService } from '@/modules/Roleplay/Character/Service/Instance/magicStudyUnlockService';

type MagicStudyGrant = Extract<Grant, { type: 'magic_study' }>;

/**
 * Сводка открытых путей волшебства: потолки изучения по scope и опыт пути.
 */
export class EditorMagicPathViewsService {
  constructor(private readonly unlocks = magicStudyUnlockService) {}

  build(build: CharacterBuild, rules: Rule[], keywords: Keyword[] = []): EditorMagicPathView[] {
    const studyUnlocks = this.unlocks.unlocksOf(build.abilities, rules);
    const granted = this.unlocks.grantedPathCodes(build.abilities, rules);
    const pathCodes = this.pathCodes(granted, studyUnlocks);
    const keywordCodeById = new Map(keywords.map((keyword) => [keyword.id, keyword.code]));

    return pathCodes
      .map((pathCode) => {
        const rule = rules.find((entry) => entry.code === pathCode && entry.type === 'magic_path');
        if (!rule) return null;
        const limits = this.limitsOf(pathCode, studyUnlocks);

        return {
          pathCode,
          pathName: rule.name,
          experience: this.unlocks.pathExperience(build.abilities, rules, pathCode, keywordCodeById),
          restrictionLabels: limits.map((limit) => this.restrictionLabel(limit.scope, limit.maxCost)),
        };
      })
      .filter((entry): entry is EditorMagicPathView => entry !== null);
  }

  private pathCodes(granted: readonly string[], unlocks: readonly MagicStudyGrant[]): string[] {
    const codes: string[] = [...granted];
    const seen = new Set(granted);
    for (const grant of unlocks) {
      if (!grant.path_code || seen.has(grant.path_code)) continue;
      seen.add(grant.path_code);
      codes.push(grant.path_code);
    }

    return codes;
  }

  private limitsOf(
    pathCode: string,
    unlocks: readonly MagicStudyGrant[],
  ): { scope: MagicStudyGrant['scope']; maxCost: number }[] {
    const byScope = new Map<MagicStudyGrant['scope'], number>();
    for (const grant of unlocks) {
      if (grant.path_code !== pathCode) continue;
      const current = byScope.get(grant.scope);
      if (current === undefined || grant.max_cost > current) byScope.set(grant.scope, grant.max_cost);
    }

    return [...byScope.entries()].map(([scope, maxCost]) => ({ scope, maxCost }));
  }

  private restrictionLabel(scope: MagicStudyGrant['scope'], maxCost: number): string {
    return scope === 'spell'
      ? `Заклинания с базовой стоимостью до ${maxCost}`
      : `Волшебство со стоимостью до ${maxCost}`;
  }
}
