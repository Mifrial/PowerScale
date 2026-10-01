import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
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
      mechanics: this.mechanicsForDraft(params),
      catalogSection: params.catalogSection ?? null,
      catalogSortOrder: params.catalogSortOrder ?? 100,
      contentStatus: params.contentStatus ?? 'needs_work',
      contentNote: params.contentNote ?? '',
      createdAt: Math.floor(Date.now() / 1000),
    };
  }

  /**
   * Смена mechanicId строки сбрасывает её payload. Чужой контекст в черновик не переносится.
   */
  private mechanicsForDraft(params: CreateDraftParams): Rule['mechanics'] {
    const loaded = params.loadedMechanics ?? [];

    return params.mechanics.map((row, index) => {
      const previous = loaded[index];
      if (previous && previous.mechanicId !== row.mechanicId) {
        return { mechanicId: row.mechanicId, mechanicPayload: null };
      }

      const payload = row.mechanicPayload;
      if (payload == null) return { mechanicId: row.mechanicId, mechanicPayload: payload ?? null };

      return { mechanicId: row.mechanicId, mechanicPayload: cloneData(payload) };
    });
  }
}
