import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

export interface DraftEntry {
  spaceId: number;
  changedRules: Record<string, Rule>;
  /** Коды правил, которые при публикации получат маркер-версию active=false. */
  removedCodes: string[];
}
