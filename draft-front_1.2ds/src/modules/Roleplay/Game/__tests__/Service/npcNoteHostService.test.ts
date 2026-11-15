import { describe, expect, it } from 'vitest';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpcRef } from '@/modules/Roleplay/Game/Dto/GameNpcRef';
import type { GameNpcListResult } from '@/modules/Roleplay/Game/Dto/GameNpcListResult';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';
import { GameNoteContextService } from '@/modules/Roleplay/Game/Service/GameNoteContextService';
import { NpcNoteHostService } from '@/modules/Roleplay/Game/Service/NpcNoteHostService';

const detail = {
  game: {
    id: 5,
    name: 'Поход',
    spaceId: 3,
    spaceCode: 'razrabotka',
    rulesRevision: 4,
  },
} as unknown as GameDetail;

const npc: GameNpcRef = { id: 11, gameId: 5, name: 'Торговец' };

function notesContext(): GameNoteContextService {
  return new GameNoteContextService(
    () => Promise.resolve([]),
    () => Promise.resolve<GameNpcListResult>({ items: [], nextCursor: null }),
    gameChatRulesContextService,
  );
}

function serviceWith(
  findNpc: (npcId: number) => Promise<GameNpcRef>,
  loadGame: (gameId: number) => Promise<GameDetail> = () => Promise.resolve(detail),
  loadRevision: () => Promise<{ rules: [] }> = () => Promise.resolve({ rules: [] }),
): NpcNoteHostService {
  return new NpcNoteHostService(findNpc, loadGame, loadRevision, notesContext());
}

describe('NpcNoteHostService', () => {
  it('строит маршрут карточки по одному npcId', async () => {
    const service = serviceWith(() => Promise.resolve(npc));

    expect(await service.resolveRoute(11)).toBe('/games/5/npcs/11');
  });

  it('отдаёт игровой контекст ссылок и кэширует поиск НПС', async () => {
    let calls = 0;
    const service = serviceWith(() => {
      calls += 1;

      return Promise.resolve(npc);
    });

    const context = await service.resolveContext(11);
    await service.resolveRoute(11);

    expect(calls).toBe(1);
    expect(context.inlineContext.gameId).toBe(5);
    expect(context.inlineContext.spaceCode).toBe('razrabotka');
    expect(context.inlineContext.entityContext).toBe('game');
    expect(context.tokenSources.map((source) => source.type)).toContain('npc');
  });

  it('не бросает на недоступном НПС и на ошибке ревизии', async () => {
    const missing = serviceWith(() => Promise.reject(new Error('НПС не найден')));

    expect(await missing.resolveRoute(11)).toBeNull();
    expect((await missing.resolveContext(11)).tokenSources).toEqual([]);

    const brokenRevision = serviceWith(
      () => Promise.resolve(npc),
      () => Promise.resolve(detail),
      () => Promise.reject(new Error('network')),
    );
    const context = await brokenRevision.resolveContext(11);

    expect(context.inlineContext.gameId).toBe(5);
    expect(context.inlineContext.rulesRevision).toBe(4);
  });
});
