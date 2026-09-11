import type { RevisionFileMechanicRef } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanicRef';

export interface RuleExternal {
  code: string;
  type: string;
  name: string;
  description: string;
  spec: object;
  keywordCodes: string[];
  mechanic: RevisionFileMechanicRef | null;
  mechanicPayload: object;
  contentStatus: string;
  contentNote: string;
  active: boolean;
  catalogSection?: string | null;
  catalogSortOrder?: number;
}
