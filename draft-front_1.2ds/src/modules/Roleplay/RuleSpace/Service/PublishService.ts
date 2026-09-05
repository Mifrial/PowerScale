import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { PublishSummary } from '@/modules/Roleplay/Space/Dto/PublishSummary';
import type { ruleValidationService, ruleDiffService } from '@/modules/Roleplay/Rule/init';

export class PublishService {
  constructor(
    private readonly ruleValidation: typeof ruleValidationService,
    private readonly ruleDiff: typeof ruleDiffService,
  ) {}

  prepare(
    published: Rule[],
    draftRules: Rule[],
    effective: Rule[],
    keywords: Keyword[],
    removedCodes: readonly string[] = [],
  ): PublishSummary {
    const diff = this.ruleDiff.classifyDraftDiff(published, draftRules, removedCodes);
    const catalog = this.ruleValidation.validateCatalog(effective, keywords);

    return {
      added: diff.added,
      changed: diff.changed,
      removed: diff.removed,
      problems: this.ruleDiff.groupProblems(catalog.items),
      spaceErrors: catalog.spaceErrors,
    };
  }
}
