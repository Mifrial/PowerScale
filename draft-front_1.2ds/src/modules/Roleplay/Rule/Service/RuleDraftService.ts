import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CreateDraftParams } from '@/modules/Roleplay/Rule/Dto/CreateDraftParams';
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';

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
