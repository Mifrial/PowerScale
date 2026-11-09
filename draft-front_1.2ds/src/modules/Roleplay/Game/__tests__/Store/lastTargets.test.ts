import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useLastTargetsStore } from '@/modules/Roleplay/Game/Store/lastTargets';

describe('lastTargets store', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('запоминает последнюю цель для пары игра + действующий', () => {
    const store = useLastTargetsStore();

    store.setTarget(1, 'character:2', 'npc:3');
    store.setTarget(1, 'npc:3', 'character:2');

    expect(store.targetOf(1, 'character:2')).toBe('npc:3');
    expect(store.targetOf(1, 'npc:3')).toBe('character:2');
    expect(store.targetOf(2, 'character:2')).toBeNull();
  });

  it('очищает цель при выборе «Воздуха»', () => {
    const store = useLastTargetsStore();

    store.setTarget(1, 'character:2', 'npc:3');
    store.setTarget(1, 'character:2', null);

    expect(store.targetOf(1, 'character:2')).toBeNull();
  });
});
