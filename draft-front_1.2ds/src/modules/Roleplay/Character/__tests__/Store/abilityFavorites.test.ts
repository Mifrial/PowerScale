import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useAbilityFavoritesStore } from '@/modules/Roleplay/Character/Store/abilityFavorites';
import { ABILITY_FAVORITES_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/abilityFavoritesConfig';

describe('abilityFavorites store persistence', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('toggle добавляет и убирает избранное, возвращая новое состояние', () => {
    const store = useAbilityFavoritesStore();
    expect(store.isFavorite(1, 'rule-a')).toBe(false);

    expect(store.toggle(1, 'rule-a')).toBe(true);
    expect(store.isFavorite(1, 'rule-a')).toBe(true);

    expect(store.toggle(1, 'rule-a')).toBe(false);
    expect(store.isFavorite(1, 'rule-a')).toBe(false);
  });

  it('избранное персонажей не пересекается', () => {
    const store = useAbilityFavoritesStore();
    store.toggle(1, 'rule-a');
    store.toggle(2, 'rule-b');

    expect(store.isFavorite(1, 'rule-a')).toBe(true);
    expect(store.isFavorite(1, 'rule-b')).toBe(false);
    expect(store.isFavorite(2, 'rule-b')).toBe(true);
  });

  it('сохраняет избранное в localStorage и восстанавливает после пересоздания стора', () => {
    const store = useAbilityFavoritesStore();
    store.toggle(1, 'rule-a');
    store.toggle(1, 'rule-b');
    store.toggle(2, 'rule-a');

    const fresh = useAbilityFavoritesStore();
    expect(fresh.isFavorite(1, 'rule-a')).toBe(true);
    expect(fresh.isFavorite(1, 'rule-b')).toBe(true);
    expect(fresh.isFavorite(2, 'rule-a')).toBe(true);
    expect(fresh.isFavorite(2, 'rule-b')).toBe(false);
  });

  it('невалидный JSON в хранилище не ломает стор', () => {
    localStorage.setItem(ABILITY_FAVORITES_STORAGE_KEY, '{not json');
    const store = useAbilityFavoritesStore();
    expect(store.isFavorite(1, 'rule-a')).toBe(false);
  });

  it('неподходящая структура игнорируется', () => {
    localStorage.setItem(ABILITY_FAVORITES_STORAGE_KEY, JSON.stringify([{ characterId: 'x' }, 42]));
    const store = useAbilityFavoritesStore();
    expect(store.isFavorite(1, 'rule-a')).toBe(false);
  });
});
