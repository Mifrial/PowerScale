import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAttackFavoritesStore } from '@/modules/Roleplay/Character/Store/attackFavorites';
import { ATTACK_FAVORITES_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/attackFavoritesConfig';

const profile = {
  itemRuleCode: 'dagger',
  profileType: 'strike' as const,
  profileIndex: 0,
};

describe('attackFavorites store', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('stores exactly one profile per character and toggles it', () => {
    const store = useAttackFavoritesStore();

    store.setFavorite('character:1', profile);
    expect(store.isFavorite('character:1', profile)).toBe(true);
    store.setFavorite('character:1', { ...profile, itemRuleCode: 'sword' });
    expect(store.isFavorite('character:1', profile)).toBe(false);
    expect(store.favoriteOf('character:1')?.itemRuleCode).toBe('sword');
  });

  it('не мигрирует записи без itemRuleCode', () => {
    localStorage.setItem(
      ATTACK_FAVORITES_STORAGE_KEY,
      JSON.stringify([{ entityKey: 'character:1', itemRuleId: 'ruka', profileType: 'strike', profileIndex: 0 }]),
    );
    const store = useAttackFavoritesStore();

    expect(store.favoriteOf('character:1')).toBeNull();
  });

  it('keeps profiles isolated by character', () => {
    const store = useAttackFavoritesStore();

    store.setFavorite('character:1', profile);
    store.setFavorite('npc:2', { ...profile, itemRuleCode: 'sword' });

    expect(store.favoriteOf('character:1')?.itemRuleCode).toBe('dagger');
    expect(store.favoriteOf('npc:2')?.itemRuleCode).toBe('sword');
  });
});
