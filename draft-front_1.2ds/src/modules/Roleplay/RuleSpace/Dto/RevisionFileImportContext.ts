import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';

/** Снимок черновика и latest для prepare, без Pinia. */
export interface RevisionFileImportContext {
  spaceId: number;
  latest: SpaceRevision<Rule>;
  removeMissing: boolean;
  draftRules: readonly Rule[];
  draftRemovedCodes: readonly string[];
  draftSections: AbilitySection[] | null;
}
