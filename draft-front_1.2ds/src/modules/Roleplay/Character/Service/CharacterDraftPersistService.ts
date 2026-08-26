import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterDraftEntry } from '@/modules/Roleplay/Character/Dto/Editor/CharacterDraftEntry';
import type { InventoryBaseline } from '@/modules/Roleplay/Character/Dto/Editor/InventoryBaseline';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { CHARACTER_DRAFT_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/characterDraftConfig';

export class CharacterDraftPersistService {
  read(): { entries: CharacterDraftEntry[]; discarded: boolean } {
    try {
      const raw = localStorage.getItem(CHARACTER_DRAFT_STORAGE_KEY);
      if (!raw) return { entries: [], discarded: false };
      const parsed: unknown = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        localStorage.removeItem(CHARACTER_DRAFT_STORAGE_KEY);

        return { entries: [], discarded: true };
      }

      const entries: CharacterDraftEntry[] = [];
      let discarded = false;
      for (const item of parsed) {
        const entry = this.parseEntry(item);
        if (entry) entries.push(entry);
        else discarded = true;
      }
      if (discarded) this.write(entries);

      return { entries, discarded };
    } catch {
      try {
        localStorage.removeItem(CHARACTER_DRAFT_STORAGE_KEY);
      } catch {
        // квота/режим — in-memory
      }

      return { entries: [], discarded: true };
    }
  }

  write(drafts: CharacterDraftEntry[]): void {
    try {
      if (drafts.length === 0) {
        localStorage.removeItem(CHARACTER_DRAFT_STORAGE_KEY);

        return;
      }
      localStorage.setItem(CHARACTER_DRAFT_STORAGE_KEY, JSON.stringify(drafts));
    } catch {
      // localStorage недоступен (квота/режим) — черновик остаётся in-memory
    }
  }

  private parseEntry(value: unknown): CharacterDraftEntry | null {
    if (typeof value !== 'object' || value === null) return null;
    const row = value as Record<string, unknown>;
    if (row.draftKey !== null && typeof row.draftKey !== 'string') return null;
    if (typeof row.build !== 'object' || row.build === null) return null;
    if (typeof row.config !== 'object' || row.config === null) return null;
    const build = row.build as CharacterBuild;
    const config = row.config as CharacterCreationConfig;
    if (typeof build.name !== 'string') return null;
    const dirty = typeof row.dirty === 'boolean' ? row.dirty : false;
    const updatedAt = typeof row.updatedAt === 'string' ? row.updatedAt : new Date().toISOString();

    const inventoryBaseline = this.parseBaseline(row.inventoryBaseline);
    if (row.inventoryBaseline !== undefined && row.inventoryBaseline !== null && !inventoryBaseline) return null;

    return {
      draftKey: row.draftKey,
      build,
      config,
      inventoryBaseline,
      dirty,
      updatedAt,
    };
  }

  private parseBaseline(value: unknown): InventoryBaseline | null {
    if (value === undefined || value === null) return null;
    if (typeof value !== 'object') return null;
    const row = value as Record<string, unknown>;
    if (!Array.isArray(row.inventory) || typeof row.money !== 'number') return null;

    return { inventory: row.inventory as InventoryItem[], money: row.money };
  }
}
