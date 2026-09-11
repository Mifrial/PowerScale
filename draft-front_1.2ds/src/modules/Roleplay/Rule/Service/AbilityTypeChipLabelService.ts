import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { AbilityTypeChipRefinement } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityTypeChipRefinement';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';

/**
 * Подпись чипа типа способности: базовый тип уточняется ключевыми словами каталога, без имён в UI.
 */
export class AbilityTypeChipLabelService {
  constructor(
    private readonly typeLabels: Record<AbilityType, string>,
    private readonly refinements: AbilityTypeChipRefinement[],
  ) {}

  label(
    type: AbilityType | null,
    keywords: readonly Keyword[],
    keywordIds: readonly number[],
    rules: readonly { type: string; code: string }[] = [],
  ): string | null {
    if (type === null) return null;
    const base = this.typeLabels[type] ?? type;
    const attached = keywords.filter((keyword) => keywordIds.includes(keyword.id));
    const codes = new Set(attached.map((keyword) => keyword.code));
    const nameByCode = new Map(attached.map((keyword) => [keyword.code, keyword.name]));
    const pathCodes = new Set(rules.filter((rule) => rule.type === 'magic_path').map((rule) => rule.code));

    for (const refinement of this.refinements) {
      if (refinement.whenType !== type || !codes.has(refinement.keywordCode)) continue;
      const keywordName = nameByCode.get(refinement.keywordCode);
      if (!keywordName) continue;
      if (refinement.mode === 'qualify') return `${base}: ${keywordName}`;
      if (refinement.mode === 'pair') {
        const companion = attached.find(
          (keyword) => keyword.code !== refinement.keywordCode && pathCodes.has(keyword.code),
        );

        return companion ? `${keywordName}: ${companion.name}` : keywordName;
      }

      return keywordName;
    }

    return base;
  }
}
