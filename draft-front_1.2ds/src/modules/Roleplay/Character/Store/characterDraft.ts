import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { CharacterDraftEntry } from '@/modules/Roleplay/Character/Dto/Editor/CharacterDraftEntry';
import type { InventoryBaseline } from '@/modules/Roleplay/Character/Dto/Editor/InventoryBaseline';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';
import { characterDraftPersistService } from '@/modules/Roleplay/Character/Service/Instance/characterDraftPersistService';

/**
 * Единый черновик редактора листа (ТР §7 «Сохранение»): один черновик на персонажа/НПС,
 * автосохранение в localStorage. `draftKey` — строковый ключ (`character:${id}` / `npc:${id}`,
 * null — новый). Для редактирования черновик инициализируется копией оригинала (copy-on-write).
 */
export const useCharacterDraftStore = defineStore('characterDraft', () => {
  const loaded = characterDraftPersistService.read();
  const storageDiscarded = ref(loaded.discarded);
  const drafts = ref<CharacterDraftEntry[]>(loaded.entries);

  function persistDrafts(): void {
    characterDraftPersistService.write(drafts.value);
  }

  function acknowledgeStorageDiscarded(): void {
    storageDiscarded.value = false;
  }

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
    persistDrafts();
  }

  /** Иммутабельно обновляет build (глубокие массивы заменяются целиком вызывающим). */
  function patchBuild(draftKey: string | null, patch: Partial<CharacterBuild>): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.build = { ...entry.build, ...patch };
    entry.dirty = true;
    entry.updatedAt = new Date().toISOString();
    persistDrafts();
  }

  function patchConfig(draftKey: string | null, patch: Partial<CharacterCreationConfig>): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.config = { ...entry.config, ...patch };
    entry.dirty = true;
    entry.updatedAt = new Date().toISOString();
    persistDrafts();
  }

  function patchInventoryBaseline(draftKey: string | null, inventoryBaseline: InventoryBaseline): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (!entry) return;
    entry.inventoryBaseline = inventoryBaseline;
    entry.updatedAt = new Date().toISOString();
    persistDrafts();
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
    persistDrafts();
  }

  /** После сохранения на бэк черновик больше не «грязный». */
  function markSaved(draftKey: string | null): void {
    const entry = drafts.value.find((e) => e.draftKey === draftKey);
    if (entry) {
      entry.dirty = false;
      persistDrafts();
    }
  }

  function discard(draftKey: string | null): void {
    const index = drafts.value.findIndex((entry) => entry.draftKey === draftKey);
    if (index !== -1) drafts.value.splice(index, 1);
    persistDrafts();
  }

  function clearAll(): void {
    drafts.value = [];
    persistDrafts();
  }

  return {
    drafts,
    storageDiscarded,
    acknowledgeStorageDiscarded,
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
