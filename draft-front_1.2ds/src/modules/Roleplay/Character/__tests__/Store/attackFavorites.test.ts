import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAttackFavoritesStore } from '@/modules/Roleplay/Character/Store/attackFavorites';

const profile = {
  itemRuleId: 'dagger',
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
    store.setFavorite('character:1', { ...profile, itemRuleId: 'sword' });
    expect(store.isFavorite('character:1', profile)).toBe(false);
    expect(store.favoriteOf('character:1')?.itemRuleId).toBe('sword');
  });

  it('keeps profiles isolated by character', () => {
    const store = useAttackFavoritesStore();

    store.setFavorite('character:1', profile);
    store.setFavorite('npc:2', { ...profile, itemRuleId: 'sword' });

    expect(store.favoriteOf('character:1')?.itemRuleId).toBe('dagger');
    expect(store.favoriteOf('npc:2')?.itemRuleId).toBe('sword');
  });
});
