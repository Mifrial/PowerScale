import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';

export interface RuleVersion {
  id: number;
  ruleId: string;
  spaceId: number;
  versionA: number;
  versionB: number;
  versionC: number;
  name: string;
  description: string;
  spec?: RuleSpec;
  keywordIds?: number[];
  mechanicId?: number | null;
  mechanic_payload?: MechanicPayload | null;
  createdAt: string;
}
