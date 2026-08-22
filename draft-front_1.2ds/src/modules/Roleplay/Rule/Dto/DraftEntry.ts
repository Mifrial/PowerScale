import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface DraftEntry {
  spaceId: number;
  changedRules: Record<string, Rule>;
}
