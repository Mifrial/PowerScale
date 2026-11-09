import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

const LAST_TARGETS_STORAGE_KEY = 'powerscale.game.last-targets.v1';

interface LastTargetEntry {
  key: string;
  targetKey: CombatEntityKey;
}

function scopeKey(gameId: number, entityKey: string): string {
  return `${gameId}:${entityKey}`;
}

function entryFromUnknown(entry: unknown): LastTargetEntry | null {
  if (typeof entry !== 'object' || entry === null) return null;
  const record = entry as Record<string, unknown>;
  if (typeof record.key !== 'string' || typeof record.targetKey !== 'string') return null;
  if (!record.targetKey.startsWith('character:') && !record.targetKey.startsWith('npc:')) return null;

  return { key: record.key, targetKey: record.targetKey as CombatEntityKey };
}

function load(): LastTargetEntry[] {
  try {
    const raw = localStorage.getItem(LAST_TARGETS_STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed.flatMap((entry) => {
      const target = entryFromUnknown(entry);

      return target ? [target] : [];
    });
  } catch {
    return [];
  }
}

function persist(entries: LastTargetEntry[]): void {
  try {
    if (entries.length === 0) {
      localStorage.removeItem(LAST_TARGETS_STORAGE_KEY);

      return;
    }
    localStorage.setItem(LAST_TARGETS_STORAGE_KEY, JSON.stringify(entries));
  } catch {
    // localStorage недоступен — последняя цель остаётся в памяти.
  }
}

/** Последняя выбранная цель боя на пару «игра + действующий» — как избранное оружия. */
export const useLastTargetsStore = defineStore('combatLastTargets', () => {
  const entries = ref<LastTargetEntry[]>(load());

  function targetOf(gameId: number, entityKey: string): CombatEntityKey | null {
    const key = scopeKey(gameId, entityKey);

    return entries.value.find((entry) => entry.key === key)?.targetKey ?? null;
  }

  function setTarget(gameId: number, entityKey: string, targetKey: CombatEntityKey | null): void {
    const key = scopeKey(gameId, entityKey);
    const rest = entries.value.filter((entry) => entry.key !== key);
    if (targetKey === null) {
      entries.value = rest;
      persist(rest);

      return;
    }

    const next = [...rest, { key, targetKey }];
    entries.value = next;
    persist(next);
  }

  return { targetOf, setTarget };
});
