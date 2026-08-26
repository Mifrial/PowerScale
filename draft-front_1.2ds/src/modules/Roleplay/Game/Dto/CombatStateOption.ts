import type { StateAggregation, StateValueType } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';

export interface CombatStateOption {
  ruleId: string;
  code: string;
  name: string;
  iconCode: string | null;
  valueType: StateValueType;
  aggregation: StateAggregation;
}
