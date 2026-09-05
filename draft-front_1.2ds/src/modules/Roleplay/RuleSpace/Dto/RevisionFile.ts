import type { SpaceRevision } from '@/modules/Roleplay/RuleSpace/Dto/SpaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface RevisionFile {
  format: string;
  formatVersion: number;
  exportedAt: string;
  revision: SpaceRevision<Rule>;
}
