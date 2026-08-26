import type { DraftEntry } from '@/modules/Roleplay/Rule/Dto/DraftEntry';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { DRAFT_RULES_STORAGE_KEY } from '@/modules/Roleplay/Rule/Constant/draftRulesConfig';

export class DraftRulesPersistService {
  read(): { entries: DraftEntry[]; discarded: boolean } {
    try {
      const raw = localStorage.getItem(DRAFT_RULES_STORAGE_KEY);
      if (!raw) return { entries: [], discarded: false };
      const parsed: unknown = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        localStorage.removeItem(DRAFT_RULES_STORAGE_KEY);

        return { entries: [], discarded: true };
      }

      const entries: DraftEntry[] = [];
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
        localStorage.removeItem(DRAFT_RULES_STORAGE_KEY);
      } catch {
        // квота/режим — in-memory
      }

      return { entries: [], discarded: true };
    }
  }

  write(drafts: DraftEntry[]): void {
    try {
      if (drafts.length === 0) {
        localStorage.removeItem(DRAFT_RULES_STORAGE_KEY);

        return;
      }
      localStorage.setItem(DRAFT_RULES_STORAGE_KEY, JSON.stringify(drafts));
    } catch {
      // localStorage недоступен (квота/режим) — черновик остаётся in-memory
    }
  }

  private parseEntry(value: unknown): DraftEntry | null {
    if (typeof value !== 'object' || value === null) return null;
    const row = value as Record<string, unknown>;
    if (typeof row.spaceId !== 'number' || typeof row.changedRules !== 'object' || row.changedRules === null) {
      return null;
    }
    const changedRules: Record<string, Rule> = {};
    for (const [ruleId, rule] of Object.entries(row.changedRules as Record<string, unknown>)) {
      if (!this.isRule(rule) || rule.id !== ruleId) return null;
      changedRules[ruleId] = rule;
    }

    const removedCodes = this.parseRemovedCodes(row.removedCodes);
    if (removedCodes === null) return null;

    return { spaceId: row.spaceId, changedRules, removedCodes };
  }

  private parseRemovedCodes(value: unknown): string[] | null {
    if (value === undefined) return [];
    if (!Array.isArray(value)) return null;
    if (!value.every((item) => typeof item === 'string')) return null;

    return value;
  }

  private isRule(value: unknown): value is Rule {
    if (typeof value !== 'object' || value === null) return false;
    const row = value as Record<string, unknown>;

    return (
      typeof row.id === 'string' &&
      typeof row.code === 'string' &&
      typeof row.type === 'string' &&
      typeof row.name === 'string' &&
      typeof row.description === 'string' &&
      typeof row.spaceId === 'number' &&
      typeof row.createdAt === 'string'
    );
  }
}
