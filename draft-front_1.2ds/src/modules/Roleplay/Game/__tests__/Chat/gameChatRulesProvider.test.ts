import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerGameApi } from '@/modules/Roleplay/Game/init';
import { registerSpaceApi } from '@/modules/Roleplay/Space/init';
import { registerRuleApi } from '@/modules/Roleplay/Rule/init';
import { mockGameApi } from '@/modules/Roleplay/Game/Mock/mockGameApi';
import { mockSpaceApi } from '@/modules/Roleplay/Space/Mock/mockSpaceApi';
import { mockRuleApi } from '@/modules/Roleplay/Rule/Mock/mockRuleApi';
import { gameChatRulesProvider } from '@/modules/Roleplay/Game/Chat/gameChatRulesProvider';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';

beforeEach(() => {
  setActivePinia(createPinia());
  serviceLocator.reset();
});

describe('gameChatRulesProvider', () => {
  it('резолвит ревизию по карточке списка, без getGame', async () => {
    const getGame = vi.fn(mockGameApi.getGame);
    const api: IGameApi = { ...mockGameApi, getGame };
    registerGameApi(api);
    registerSpaceApi(mockSpaceApi);
    registerRuleApi(mockRuleApi);

    const games = await mockGameApi.getGames();
    const sample = games.find((game) => game.gameChatId !== null);
    expect(sample?.gameChatId).toEqual(expect.any(Number));

    const context = await gameChatRulesProvider.resolve('game', sample!.gameChatId!);

    expect(getGame).not.toHaveBeenCalled();
    expect(context).not.toBeNull();
    expect(context?.spaceId).toBe(sample!.spaceId);
    expect(context?.rulesRevision).toBe(sample!.rulesRevision);
    expect(Object.keys(context?.tokenLabels ?? {}).length).toBeGreaterThan(0);
  });

  it('резолвит обсуждение игры по тому же списку', async () => {
    const getGame = vi.fn(mockGameApi.getGame);
    registerGameApi({ ...mockGameApi, getGame });
    registerSpaceApi(mockSpaceApi);
    registerRuleApi(mockRuleApi);

    const games = await mockGameApi.getGames();
    const sample = games.find((game) => game.discussionChatId !== null);
    expect(sample?.discussionChatId).toEqual(expect.any(Number));

    const context = await gameChatRulesProvider.resolve('game_discussion', sample!.discussionChatId!);

    expect(getGame).not.toHaveBeenCalled();
    expect(context?.spaceId).toBe(sample!.spaceId);
  });

  it('неизвестный чат — null без деталок', async () => {
    const getGame = vi.fn(mockGameApi.getGame);
    registerGameApi({ ...mockGameApi, getGame });
    registerSpaceApi(mockSpaceApi);
    registerRuleApi(mockRuleApi);

    await expect(gameChatRulesProvider.resolve('game', 999_999)).resolves.toBeNull();
    expect(getGame).not.toHaveBeenCalled();
  });
});
