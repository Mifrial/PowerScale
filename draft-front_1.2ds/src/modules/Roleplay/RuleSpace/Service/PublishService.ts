import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { PublishDiff } from '@/modules/Roleplay/Rule/Dto/PublishDiff';
import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { PublishSummary } from '@/modules/Roleplay/RuleSpace/Dto/PublishSummary';
import type { ProblemEntry } from '@/modules/Roleplay/Rule/Dto/ProblemEntry';
import type { ruleValidationService, ruleDiffService } from '@/modules/Roleplay/Rule/init';

/**
 * Готовит сводку публикации черновика правил и каталога.
 */
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
    catalogDirty = false,
  ): PublishSummary {
    const diff = this.ruleDiff.classifyDraftDiff(published, draftRules, removedCodes);

    return this.prepareFromDiff(diff, effective, keywords, catalogDirty);
  }

  /** Собирает сводку по уже вычисленному диффу — единому для списка и диалога публикации. */
  prepareFromDiff(diff: PublishDiff, effective: Rule[], keywords: Keyword[], catalogDirty = false): PublishSummary {
    const catalog = this.ruleValidation.validateCatalog(effective, keywords);

    return {
      added: diff.added,
      changed: diff.changed,
      removed: diff.removed,
      problems: this.ruleDiff.groupProblems(catalog.items),
      spaceErrors: catalog.spaceErrors,
      catalogDirty,
    };
  }

  mergeServerProblems(summary: PublishSummary, details: unknown): PublishSummary {
    const problems = this.serverProblems(details);
    if (problems.length === 0) {
      return summary;
    }

    return { ...summary, problems };
  }

  private serverProblems(details: unknown): ProblemEntry[] {
    if (details === null || typeof details !== 'object' || !('problems' in details)) {
      return [];
    }

    const rows = details.problems;
    if (!Array.isArray(rows)) {
      return [];
    }

    const problems: ProblemEntry[] = [];
    for (const row of rows) {
      if (row === null || typeof row !== 'object') continue;
      const ruleCode = 'ruleCode' in row && typeof row.ruleCode === 'string' ? row.ruleCode : '';
      const ruleName = 'ruleName' in row && typeof row.ruleName === 'string' ? row.ruleName : '';
      const messages =
        'messages' in row && Array.isArray(row.messages)
          ? row.messages.filter((message: unknown): message is string => typeof message === 'string')
          : [];
      if (ruleCode !== '' && messages.length > 0) {
        problems.push({ ruleCode, ruleName, messages });
      }
    }

    return problems;
  }
}
