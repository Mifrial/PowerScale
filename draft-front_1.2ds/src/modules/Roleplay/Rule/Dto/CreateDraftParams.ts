import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';

export interface CreateDraftParams {
  isEdit: boolean;
  id: number | null;
  type: RuleType;
  name: string;
  code: string;
  loadedCode: string;
  description: string;
  spaceId: number;
  spec?: RuleSpec | null;
  keywordIds: number[];
  mechanicId?: number | null;
  /** mechanicId на момент загрузки формы; сверка решает, сохранять ли payload. */
  loadedMechanicId?: number | null;
  mechanicPayload?: MechanicPayload | null;
  catalogSection?: string | null;
  catalogSortOrder?: number;
  contentStatus?: string;
  contentNote?: string;
}
