import { describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { watchEffect } from 'vue';
import { registerGameApi } from '@/modules/Roleplay/Game/init';
import { mockGameApi } from '@/modules/Roleplay/Game/Mock/mockGameApi';
import { useGameStore } from '@/modules/Roleplay/Game/Store/games';

describe('games store: мутация участников', () => {
  it('updateMember реактивно виден (регрессия алиасинга с мок-бэком)', async () => {
    setActivePinia(createPinia());
    registerGameApi(mockGameApi);
    const store = useGameStore();
    await store.fetchGame(1);

    const idx = store.currentGame!.members.findIndex((m) => m.userId === 4);
    let seenRole: string | null = null;
    const stop = watchEffect(() => {
      seenRole = store.currentGame?.members[idx]?.role ?? null;
    });

    // Мок мутирует общий gameDetails; стор должен держать снимок, иначе присвоение того же
    // объекта не триггерит реактивность (hasChanged false) и подписчики не обновятся.
    const updated = await mockGameApi.updateGameMember(1, 4, { role: 'gm', permissions: ['game.moderate'] });
    store.updateMember(updated);
    await Promise.resolve();

    expect(store.currentGame?.members[idx]?.role).toBe('gm');
    expect(store.currentGame?.members[idx]?.permissions).toContain('game.moderate');
    expect(seenRole).toBe('gm');
    stop();
  });
});
