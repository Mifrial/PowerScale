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

    const items = [
      ...this.ruleValidation
        .validateRuleReferences(
          effective,
          keywords.map((t) => ({ code: t.code, name: t.name })),
        )
        .map((e) => ({
          ruleCode: e.ruleCode,
          ruleName: e.ruleName,
          message: this.ruleValidation.formatReferenceError(e),
        })),
      ...this.ruleValidation.validateRuleCodeFormat(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateAbilityStructure(effective, keywords).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateRaceStructure(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateSpeciesStructure(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateItemModifierStructure(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateCheckStructure(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
      ...this.ruleValidation.validateDamageTypeStructure(effective).map((e) => ({
        ruleCode: e.ruleCode,
        ruleName: e.ruleName,
        message: e.message,
      })),
    ];

    const cycle = this.ruleValidation.findSpeciesCycle(effective);

    return {
      added: diff.added,
      changed: diff.changed,
      removed: diff.removed,
      problems: this.ruleDiff.groupProblems(items),
      spaceErrors: cycle ? [this.ruleValidation.formatSpeciesCycle(cycle)] : [],
    };
  }
}
