import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CreateDraftParams } from '@/modules/Roleplay/Rule/Dto/CreateDraftParams';
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';

export class RuleDraftService {
  createDraft(params: CreateDraftParams): Rule {
    return {
      id: params.isEdit ? params.id : null,
      code: params.isEdit ? params.loadedCode : params.code.trim() || slugify(params.name),
      type: params.type,
      name: params.name,
      description: params.description,
      spaceId: params.spaceId,
      spec: params.spec ?? undefined,
      keywordIds: params.keywordIds,
      mechanicId: params.mechanicId,
      mechanicPayload: this.payloadForDraft(params),
      catalogSection: params.catalogSection ?? null,
      catalogSortOrder: params.catalogSortOrder ?? 100,
      contentStatus: params.contentStatus ?? 'needs_work',
      contentNote: params.contentNote ?? '',
      createdAt: Math.floor(Date.now() / 1000),
    };
  }

  /**
   * Тот же mechanicId — исходный payload. Смена или очистка id сбрасывает его:
   * чужой контекст механики в черновик не переносится.
   */
  private payloadForDraft(params: CreateDraftParams): Rule['mechanicPayload'] {
    const mechanicId = params.mechanicId ?? null;
    const loadedMechanicId = params.loadedMechanicId ?? null;
    if (mechanicId !== loadedMechanicId) return null;
    const payload = params.mechanicPayload;
    if (payload == null) return payload;

    return structuredClone(payload);
  }
}
