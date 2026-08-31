import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface ResolvedReference {
  ruleCode: string;
  name: string;
  href: string | null;
  isResolved: boolean;
  rule: Rule | null;
}
