import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { CharacterDraftEntry } from '@/modules/Roleplay/Character/Dto/Editor/CharacterDraftEntry';
import type { InventoryBaseline } from '@/modules/Roleplay/Character/Dto/Editor/CharacterDraftEntry';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { CHARACTER_DRAFT_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/characterDraftConfig';

function loadDrafts(): CharacterDraftEntry[] {
  try {
    const raw = localStorage.getItem(CHARACTER_DRAFT_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed.filter(
      (entry): entry is CharacterDraftEntry =>
        typeof entry === 'object' &&
        entry !== null &&
        (entry.draftKey === null || typeof entry.draftKey === 'string') &&
        typeof entry.build === 'object' &&
        typeof entry.config === 'object',
    );
  } catch {
    return [];
  }
}

function persistDrafts(drafts: CharacterDraftEntry[]): void {
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

/**
 * Единый черновик редактора листа (ТР §7 «Сохранение»): один черновик на персонажа/НПС,
 * автосохранение в localStorage. `draftKey` — строковый ключ (`character:${id}` / `npc:${id}`,
 * null — новый). Для редактирования черновик инициализируется копией оригинала (copy-on-write).
 */
export const useCharacterDraftStore = defineStore('characterDraft', () => {
  const drafts = ref<CharacterDraftEntry[]>(loadDrafts());

  function draftOf(draftKey: string | null): CharacterDraftEntry | undefined {
    return drafts.value.find((entry) => entry.draftKey === draftKey);
  }

  const hasDraft = computed(() => (draftKey: string | null) => draftOf(draftKey) !== undefined);

  /** Создаёт или перезаписывает черновик (например, при старте редактирования). */
  function initDraft(
    draftKey: string | null,
    build: CharacterBuild,
    config: CharacterCreationConfig,
    inventoryBaseline: InventoryBaseline | null = null,
  ): void {
    const index = drafts.value.findIndex((entry) => entry.draftKey === draftKey);
    const entry: CharacterDraftEntry = {
      draftKey,
      build,
      config,
      inventoryBaseline,
      dirty: false,
      updatedAt: new Date().toISOString(),
    };
    if (index === -1) drafts.value.push(entry);
    else drafts.value[index] = entry;
    persistDrafts(drafts.value);
  }

  /** Иммутабельно обновляет build (глубокие массивы заменяются целиком вызывающим). */
  function patchBuild(draftKey: string | null, patch: Partial<CharacterBuild>): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.build = { ...entry.build, ...patch };
    entry.dirty = true;
    entry.updatedAt = new Date().toISOString();
    persistDrafts(drafts.value);
  }

  function patchConfig(draftKey: string | null, patch: Partial<CharacterCreationConfig>): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.config = { ...entry.config, ...patch };
    entry.dirty = true;
    entry.updatedAt = new Date().toISOString();
    persistDrafts(drafts.value);
  }

  function patchInventoryBaseline(draftKey: string | null, inventoryBaseline: InventoryBaseline): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.inventoryBaseline = inventoryBaseline;
    entry.updatedAt = new Date().toISOString();
    persistDrafts(drafts.value);
  }

  /**
   * Фиксация базовой линии «Инвентаря» при первом входе на шаг (R2). Для нового листа
   * деньги донормируются до effectiveMoney (учитывает особенности богатства «Личности») и
   * фиксируется пустая базовая линия; для edit линия уже задана в initDraft — no-op.
   */
  function ensureInventoryBaseline(draftKey: string | null, effectiveMoney: number): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry || entry.inventoryBaseline) return;

    const build = entry.build;
    const inventory: InventoryItem[] = draftKey === null ? [] : build.inventory.map((item) => ({ ...item }));
    const money = draftKey === null ? effectiveMoney : build.money;
    const baseline: InventoryBaseline = { inventory, money };

    if (draftKey === null) {
      entry.build = { ...build, money: effectiveMoney };
      entry.dirty = true;
    }
    entry.inventoryBaseline = baseline;
    entry.updatedAt = new Date().toISOString();
    persistDrafts(drafts.value);
  }

  /** После сохранения на бэк черновик больше не «грязный». */
  function markSaved(draftKey: string | null): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (entry) {
      entry.dirty = false;
      persistDrafts(drafts.value);
    }
  }

  function discard(draftKey: string | null): void {
    const index = drafts.value.findIndex((entry) => entry.draftKey === draftKey);
    if (index !== -1) drafts.value.splice(index, 1);
    persistDrafts(drafts.value);
  }

  function clearAll(): void {
    drafts.value = [];
    persistDrafts(drafts.value);
  }

  return {
    drafts,
    draftOf,
    hasDraft,
    initDraft,
    patchBuild,
    patchConfig,
    patchInventoryBaseline,
    ensureInventoryBaseline,
    markSaved,
    discard,
    clearAll,
  };
});
