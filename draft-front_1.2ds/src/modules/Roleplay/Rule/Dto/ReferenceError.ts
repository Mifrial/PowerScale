import type { ReferenceTargetType } from '@/modules/Roleplay/Rule/Dto/ReferenceTargetType';

export interface ReferenceError {
  ruleName: string;
  ruleCode: string;
  refCode: string;
  expectedType: ReferenceTargetType;
}
