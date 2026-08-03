import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';

export interface UpdateRuleData {
  name?: string;
  description?: string;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanicId?: number | null;
}
