import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';

export interface UpdateRuleData {
  name?: string;
  description?: string;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanicId?: number | null;
  mechanicPayload?: MechanicPayload | null;
}
