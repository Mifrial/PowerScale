import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';

export interface UpdateRuleData {
  name?: string;
  description?: string;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanics?: RuleMechanicRef[];
}
