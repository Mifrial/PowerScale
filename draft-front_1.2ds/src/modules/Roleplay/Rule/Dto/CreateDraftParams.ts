import type { RuleMechanicRef } from '@/modules/Roleplay/Rule/Dto/RuleMechanicRef';
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
  mechanics: RuleMechanicRef[];
  /** Список на момент загрузки формы; сверка решает, сохранять ли payload строки. */
  loadedMechanics?: RuleMechanicRef[];
  catalogSection?: string | null;
  catalogSortOrder?: number;
  contentStatus?: string;
  contentNote?: string;
}
