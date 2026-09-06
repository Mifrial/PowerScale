import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { RevisionFileKeyword } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileKeyword';
import type { RevisionFileMechanic } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanic';
import type { RevisionFileSource } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileSource';
import type { RuleExternal } from '@/modules/Roleplay/RuleSpace/Dto/RuleExternal';

export interface RevisionFile {
  format: string;
  formatVersion: number;
  exportedAt: number;
  source: RevisionFileSource;
  keywords: RevisionFileKeyword[];
  mechanics: RevisionFileMechanic[];
  sections: AbilitySection[];
  rules: RuleExternal[];
}
