import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileImportDiff } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportDiff';
import type { RevisionFileImportWarning } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileImportWarning';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Результат разбора файла против latest, без записи Pinia. */
export interface RevisionFileImportPreview {
  file: RevisionFile;
  rules: Rule[];
  sections: AbilitySection[];
  diff: RevisionFileImportDiff;
  warnings: RevisionFileImportWarning[];
  hasTargetDraft: boolean;
  occupiedCodes: string[];
  draftRuleCodes: string[];
  currentRemovedCodes: string[];
  publishedCodes: string[];
  removeMissing: boolean;
  draftSectionsDirty: boolean;
}
