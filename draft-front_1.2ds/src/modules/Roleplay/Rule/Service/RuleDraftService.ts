import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType';
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';

export interface CreateDraftParams {
  isEdit: boolean;
  id: string;
  type: RuleType;
  name: string;
  code: string;
  loadedCode: string;
  description: string;
  spaceId: number;
  spec?: RuleSpec | null;
  keywordIds: number[];
  mechanicId?: number | null;
}

export class RuleDraftService {
  createDraft(params: CreateDraftParams): Rule {
    return {
      id: params.isEdit ? params.id : `draft-${Date.now()}`,
      code: params.isEdit ? params.loadedCode : params.code.trim() || slugify(params.name),
      type: params.type,
      name: params.name,
      description: params.description,
      spaceId: params.spaceId,
      spec: params.spec ?? undefined,
      keywordIds: params.keywordIds,
      mechanicId: params.mechanicId,
      createdAt: new Date().toISOString(),
    };
  }
}

export const ruleDraftService = new RuleDraftService();
