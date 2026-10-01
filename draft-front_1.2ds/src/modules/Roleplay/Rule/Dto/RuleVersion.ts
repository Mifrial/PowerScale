import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';

export interface RuleVersion {
  id: number;
  ruleId: number;
  spaceId: number;
  versionA: number;
  versionB: number;
  versionC: number;
  name: string;
  description: string;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanics?: RuleMechanicRef[];
  createdAt: number;
}
