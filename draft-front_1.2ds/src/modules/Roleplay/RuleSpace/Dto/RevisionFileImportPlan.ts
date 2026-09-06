import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFileSectionAction } from '@/modules/Roleplay/RuleSpace/Enum/RevisionFileSectionAction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Команды apply импорта: страница пишет сторы. */
export interface RevisionFileImportPlan {
  saveRules: Rule[];
  clearDraftCodes: string[];
  removedCodes: string[];
  shouldSetRemovedCodes: boolean;
  sectionAction: RevisionFileSectionAction;
  sections: AbilitySection[];
  isNoOp: boolean;
  summary: string;
}
