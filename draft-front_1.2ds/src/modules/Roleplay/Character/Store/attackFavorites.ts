import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { CharacterAttackFavorite } from '@/modules/Roleplay/Character/Dto/CharacterAttackFavorite';
import { ATTACK_FAVORITES_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/attackFavoritesConfig';

function load(): CharacterAttackFavorite[] {
  try {
    const raw = localStorage.getItem(ATTACK_FAVORITES_STORAGE_KEY);
    if (!raw) return [];
    const parsed: unknown = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed.filter(
      (entry): entry is CharacterAttackFavorite =>
        typeof entry === 'object' &&
        entry !== null &&
        typeof entry.entityKey === 'string' &&
        typeof entry.itemRuleId === 'string' &&
        (entry.profileType === 'strike' || entry.profileType === 'throw' || entry.profileType === 'shoot') &&
        typeof entry.profileIndex === 'number',
    );
  } catch {
    return [];
  }
}

function persist(entries: CharacterAttackFavorite[]): void {
  try {
    if (entries.length === 0) {
      localStorage.removeItem(ATTACK_FAVORITES_STORAGE_KEY);

      return;
    }
    localStorage.setItem(ATTACK_FAVORITES_STORAGE_KEY, JSON.stringify(entries));
  } catch {
    // localStorage недоступен — избранное остаётся в памяти.
  }
}

function profileKey(profile: Pick<CharacterAttackFavorite, 'itemRuleId' | 'profileType' | 'profileIndex'>): string {
  return `${profile.itemRuleId}:${profile.profileType}:${profile.profileIndex}`;
}

export const useAttackFavoritesStore = defineStore('attackFavorites', () => {
  const entries = ref<CharacterAttackFavorite[]>(load());

  function favoriteOf(entityKey: string): CharacterAttackFavorite | null {
    return entries.value.find((entry) => entry.entityKey === entityKey) ?? null;
  }

  function isFavorite(
    entityKey: string,
    profile: Pick<CharacterAttackFavorite, 'itemRuleId' | 'profileType' | 'profileIndex'>,
  ): boolean {
    const favorite = favoriteOf(entityKey);

    return favorite !== null && profileKey(favorite) === profileKey(profile);
  }

  function setFavorite(
    entityKey: string,
    profile: Pick<CharacterAttackFavorite, 'itemRuleId' | 'profileType' | 'profileIndex'>,
  ): void {
    const next = [...entries.value.filter((entry) => entry.entityKey !== entityKey), { entityKey, ...profile }];
    entries.value = next;
    persist(next);
  }

  return { favoriteOf, isFavorite, setFavorite };
});
