import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface ResolvedReference {
  ruleId: string;
  name: string;
  href: string | null;
  isResolved: boolean;
  rule: Rule | null;
}
