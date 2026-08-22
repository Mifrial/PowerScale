import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { CharacterAbilityFavorites } from '@/modules/Roleplay/Character/Dto/CharacterAbilityFavorites';
import { ABILITY_FAVORITES_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/abilityFavoritesConfig';

function load(): CharacterAbilityFavorites[] {
  try {
    const raw = localStorage.getItem(ABILITY_FAVORITES_STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed.filter(
      (entry): entry is CharacterAbilityFavorites =>
        typeof entry === 'object' &&
        entry !== null &&
        typeof entry.characterId === 'number' &&
        Array.isArray(entry.ruleIds) &&
        entry.ruleIds.every((id: unknown) => typeof id === 'string'),
    );
  } catch {
    return [];
  }
}

function persist(entries: CharacterAbilityFavorites[]): void {
  try {
    if (entries.length === 0) {
      localStorage.removeItem(ABILITY_FAVORITES_STORAGE_KEY);

      return;
    }
    localStorage.setItem(ABILITY_FAVORITES_STORAGE_KEY, JSON.stringify(entries));
  } catch {
    // localStorage недоступен (квота/режим) — избранное остаётся in-memory
  }
}

export const useAbilityFavoritesStore = defineStore('abilityFavorites', () => {
  const entries = ref<CharacterAbilityFavorites[]>(load());

  function entryOf(characterId: number): CharacterAbilityFavorites | undefined {
    return entries.value.find((entry) => entry.characterId === characterId);
  }

  function isFavorite(characterId: number, ruleId: string): boolean {
    return entryOf(characterId)?.ruleIds.includes(ruleId) ?? false;
  }

  /** Переключает избранность способности; возвращает true, если теперь она избранная. */
  function toggle(characterId: number, ruleId: string): boolean {
    let entry = entryOf(characterId);
    if (!entry) {
      entry = { characterId, ruleIds: [] };
      entries.value.push(entry);
    }

    const index = entry.ruleIds.indexOf(ruleId);
    if (index === -1) {
      entry.ruleIds.push(ruleId);
    } else {
      entry.ruleIds.splice(index, 1);
    }
    persist(entries.value);

    return index === -1;
  }

  return {
    entries,
    isFavorite,
    toggle,
  };
});
