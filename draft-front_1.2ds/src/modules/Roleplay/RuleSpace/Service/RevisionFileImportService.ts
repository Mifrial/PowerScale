import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileImportContext } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportContext';
import type { RevisionFileImportDiff } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportDiff';
import type { RevisionFileImportPlan } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportPlan';
import type { RevisionFileImportPreview } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportPreview';
import type { RevisionFileImportWarning } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportWarning';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevisionMeta';
import type { RevisionFileConflictPolicy } from '@/modules/Roleplay/RuleSpace/Enum/RevisionFileConflictPolicy';
import { REVISION_FILE_PROBLEM_CODE } from '@/modules/Roleplay/RuleSpace/Constant/REVISION_FILE_PROBLEM_CODE';
import type { AbilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/AbilitySectionTreeService';
import { RevisionFileProblemError } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileProblemError';
import type { RevisionFileService } from '@/modules/Roleplay/RuleSpace/Service/RevisionFileService';

/**
 * Импорт v3 в черновик: prepare против latest и план apply по политике конфликта.
 */
export class RevisionFileImportService {
  constructor(
    private readonly files: RevisionFileService,
    private readonly tree: AbilitySectionTreeService,
    private readonly getRevisions: (spaceId: number, signal?: AbortSignal) => Promise<SpaceRevisionMeta[]>,
    private readonly getRevision: (
      spaceId: number,
      revision: number,
      signal?: AbortSignal,
    ) => Promise<SpaceRevision<Rule>>,
    private readonly isKnownType: (type: string) => boolean,
    private readonly isKnownContentStatus: (status: string) => boolean,
  ) {}

  emptySlice(): SpaceRevision<Rule> {
    return { revision: 0, publishedAt: 0, spaceCode: '', spaceName: '', rules: [], sections: [] };
  }

  async loadLatest(spaceId: number, signal?: AbortSignal): Promise<SpaceRevision<Rule>> {
    const meta = await this.getRevisions(spaceId, signal);
    const revision = meta.length > 0 ? meta[meta.length - 1].revision : 0;
    if (revision < 1) {
      return this.emptySlice();
    }

    return this.getRevision(spaceId, revision, signal);
  }

  prepare(
    file: RevisionFile,
    catalogs: { keywords: readonly Keyword[]; mechanics: readonly Mechanic[] },
    context: RevisionFileImportContext,
  ): RevisionFileImportPreview {
    const rules = this.files.materializeRules(file, context.spaceId, catalogs.keywords, catalogs.mechanics);
    this.assertSections(file.sections, rules);
    const sections = this.tree.normalize(file.sections);
    const sectionsDiffer = !this.tree.sameCatalog(sections, context.latest.sections);
    const overlay = this.files.diffAgainstPublished(rules, context.latest.rules, context.spaceId, {
      removeMissing: context.removeMissing,
      existingRemovedCodes: context.draftRemovedCodes,
    });
    const diff: RevisionFileImportDiff = { ...overlay, sectionsDiffer };
    const occupiedCodes = [...new Set([...context.draftRules.map((rule) => rule.code), ...context.draftRemovedCodes])];
    const draftSectionsDirty =
      context.draftSections !== null && !this.tree.sameCatalog(context.draftSections, context.latest.sections);

    return {
      file,
      rules,
      sections,
      diff,
      warnings: this.collectWarnings(file),
      hasTargetDraft: occupiedCodes.length > 0 || draftSectionsDirty,
      occupiedCodes,
      draftRuleCodes: context.draftRules.map((rule) => rule.code),
      currentRemovedCodes: [...context.draftRemovedCodes],
      publishedCodes: context.latest.rules.map((rule) => rule.code),
      removeMissing: context.removeMissing,
      draftSectionsDirty,
    };
  }

  planApply(preview: RevisionFileImportPreview, policy: RevisionFileConflictPolicy): RevisionFileImportPlan {
    if (!preview.hasTargetDraft || policy === 'prefer_file') {
      return this.planPreferFile(preview);
    }

    return this.planPreferDraft(preview);
  }

  private planPreferDraft(preview: RevisionFileImportPreview): RevisionFileImportPlan {
    const occupied = new Set(preview.occupiedCodes);
    const fileCodes = new Set(preview.rules.map((rule) => rule.code));
    const saveRules = [...preview.diff.changed, ...preview.diff.added].filter((rule) => !occupied.has(rule.code));
    const removedCodes = [...preview.currentRemovedCodes.filter((code) => !fileCodes.has(code))];
    if (preview.removeMissing) {
      for (const code of preview.publishedCodes) {
        if (!fileCodes.has(code) && !occupied.has(code) && !removedCodes.includes(code)) {
          removedCodes.push(code);
        }
      }
    }
    const sectionAction = preview.draftSectionsDirty || !preview.diff.sectionsDiffer ? 'keep' : 'save';

    return this.finishPlan(preview, {
      saveRules,
      clearDraftCodes: [],
      removedCodes,
      sectionAction,
    });
  }

  private planPreferFile(preview: RevisionFileImportPreview): RevisionFileImportPlan {
    const changedCodes = new Set([...preview.diff.added, ...preview.diff.changed].map((rule) => rule.code));
    const draftRuleCodes = new Set(preview.draftRuleCodes);
    const clearDraftCodes = preview.rules
      .map((rule) => rule.code)
      .filter((code) => !changedCodes.has(code) && draftRuleCodes.has(code));
    let sectionAction: RevisionFileImportPlan['sectionAction'] = 'keep';
    if (preview.diff.sectionsDiffer) sectionAction = 'save';
    else if (preview.draftSectionsDirty) sectionAction = 'discard';

    return this.finishPlan(preview, {
      saveRules: [...preview.diff.changed, ...preview.diff.added],
      clearDraftCodes,
      removedCodes: preview.diff.removedCodes,
      sectionAction,
    });
  }

  private finishPlan(
    preview: RevisionFileImportPreview,
    partial: {
      saveRules: Rule[];
      clearDraftCodes: string[];
      removedCodes: string[];
      sectionAction: RevisionFileImportPlan['sectionAction'];
    },
  ): RevisionFileImportPlan {
    const removedChanged = !this.sameCodeList(partial.removedCodes, preview.currentRemovedCodes);
    const isNoOp =
      partial.saveRules.length === 0 &&
      partial.clearDraftCodes.length === 0 &&
      partial.sectionAction === 'keep' &&
      !removedChanged;
    const plannedDiff: RevisionFileImportDiff = {
      added: partial.saveRules.filter((rule) => preview.diff.added.some((item) => item.code === rule.code)),
      changed: partial.saveRules.filter((rule) => preview.diff.changed.some((item) => item.code === rule.code)),
      unchangedCount: preview.diff.unchangedCount,
      removedCodes: partial.removedCodes,
      sectionsDiffer: partial.sectionAction === 'save',
    };

    return {
      saveRules: partial.saveRules,
      clearDraftCodes: partial.clearDraftCodes,
      removedCodes: partial.removedCodes,
      shouldSetRemovedCodes: removedChanged,
      sectionAction: partial.sectionAction,
      sections: preview.sections,
      isNoOp,
      summary: this.files.formatImportSummary(plannedDiff),
    };
  }

  private collectWarnings(file: RevisionFile): RevisionFileImportWarning[] {
    const warnings: RevisionFileImportWarning[] = [];
    for (let index = 0; index < file.rules.length; index += 1) {
      const rule = file.rules[index];
      const path = `/rules/${index}`;
      if (!this.isKnownType(rule.type)) {
        warnings.push({ path: `${path}/type`, message: `Неизвестный type: ${rule.type}` });
      }
      if (!this.isKnownContentStatus(rule.contentStatus)) {
        warnings.push({
          path: `${path}/contentStatus`,
          message: `Неизвестный contentStatus: ${rule.contentStatus}`,
        });
      }
    }

    return warnings;
  }

  private assertSections(sections: AbilitySection[], rules: Rule[]): void {
    const treeErrors = this.tree.validate(sections);
    if (treeErrors[0]) {
      throw new RevisionFileProblemError({
        code: REVISION_FILE_PROBLEM_CODE.format,
        path: '/sections',
        stage: 'format',
        message: treeErrors[0],
      });
    }
    const placementErrors = this.tree.validateRuleSections(rules, sections);
    if (placementErrors[0]) {
      throw new RevisionFileProblemError({
        code: REVISION_FILE_PROBLEM_CODE.format,
        path: '/rules',
        stage: 'format',
        message: placementErrors[0],
      });
    }
  }

  private sameCodeList(left: readonly string[], right: readonly string[]): boolean {
    if (left.length !== right.length) return false;
    const rightSet = new Set(right);

    return left.every((code) => rightSet.has(code));
  }
}
