import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import { SECTION_DRAFTS_STORAGE_KEY } from '@/modules/Roleplay/RuleSpace/Constant/sectionDraftsConfig';

/**
 * Читает и пишет клиентский черновик дерева секций.
 */
export class SectionDraftPersistService {
  read(): { bySpaceId: Record<string, AbilitySection[]>; discarded: boolean } {
    try {
      const raw = localStorage.getItem(SECTION_DRAFTS_STORAGE_KEY);
      if (!raw) return { bySpaceId: {}, discarded: false };
      const parsed: unknown = JSON.parse(raw);
      if (typeof parsed !== 'object' || parsed === null || Array.isArray(parsed)) {
        localStorage.removeItem(SECTION_DRAFTS_STORAGE_KEY);

        return { bySpaceId: {}, discarded: true };
      }
      const bySpaceId: Record<string, AbilitySection[]> = {};
      let discarded = false;
      for (const [key, value] of Object.entries(parsed as Record<string, unknown>)) {
        const sections = this.parseSections(value);
        if (sections) bySpaceId[key] = sections;
        else discarded = true;
      }
      if (discarded) this.write(bySpaceId);

      return { bySpaceId, discarded };
    } catch {
      try {
        localStorage.removeItem(SECTION_DRAFTS_STORAGE_KEY);
      } catch {
        // квота/режим — in-memory
      }

      return { bySpaceId: {}, discarded: true };
    }
  }

  write(bySpaceId: Record<string, AbilitySection[]>): void {
    try {
      if (Object.keys(bySpaceId).length === 0) {
        localStorage.removeItem(SECTION_DRAFTS_STORAGE_KEY);

        return;
      }
      localStorage.setItem(SECTION_DRAFTS_STORAGE_KEY, JSON.stringify(bySpaceId));
    } catch {
      // localStorage недоступен
    }
  }

  private parseSections(value: unknown): AbilitySection[] | null {
    if (!Array.isArray(value)) return null;
    const sections: AbilitySection[] = [];
    for (const item of value) {
      if (typeof item !== 'object' || item === null) return null;
      const row = item as Record<string, unknown>;
      if (
        typeof row.code !== 'string' ||
        typeof row.name !== 'string' ||
        (row.parentCode !== null && typeof row.parentCode !== 'string') ||
        typeof row.sortOrder !== 'number'
      ) {
        return null;
      }
      sections.push({
        code: row.code,
        name: row.name,
        parentCode: row.parentCode,
        sortOrder: row.sortOrder,
        catalogRootFor: typeof row.catalogRootFor === 'string' ? row.catalogRootFor : undefined,
      });
    }

    return sections;
  }
}
