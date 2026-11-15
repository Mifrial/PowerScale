import { describe, expect, it } from 'vitest';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNpcListQuery } from '@/modules/Roleplay/Game/Dto/GameNpcListQuery';
import type { GameNpcListResult } from '@/modules/Roleplay/Game/Dto/GameNpcListResult';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import { gameChatRulesContextService } from '@/modules/Roleplay/Game/Service/Instance/gameChatRulesContextService';
import { GameNoteContextService } from '@/modules/Roleplay/Game/Service/GameNoteContextService';

function membership(
  id: number,
  name: string,
  status: GameCharacterMembership['membershipStatus'],
): GameCharacterMembership {
  return {
    gameId: 7,
    characterId: id,
    characterName: name,
    characterOwnerId: 1,
    characterOwnerName: 'Игрок',
    role: 'player',
    membershipStatus: status,
    approvedCharacterVersion: null,
    reviewState: 'clean',
    membershipRevision: 1,
    returnedAt: null,
    returnReason: null,
    returnMessageId: null,
    overlay: null,
    visibility: [],
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '',
  };
}

const memberships = [
  membership(1, 'Гаррик', 'active'),
  membership(2, 'Бородач', 'submitted'),
  membership(3, 'Ушедший', 'left'),
];

const npcQueries: GameNpcListQuery[] = [];

function npcsOf(query: GameNpcListQuery): Promise<GameNpcListResult> {
  npcQueries.push(query);

  return Promise.resolve({
    items: [
      { id: 11, name: 'Торговец', actualVersion: 1 },
      { id: 12, name: 'Наёмник', actualVersion: 1 },
    ],
    nextCursor: null,
  });
}

function sourceOf(sources: ITokenSource[], type: string): ITokenSource {
  const source = sources.find((candidate) => candidate.type === type);

  if (!source) throw new Error(`Нет источника ${type}`);

  return source;
}

function buildService(
  loadNpcs: (query: GameNpcListQuery) => Promise<GameNpcListResult> = npcsOf,
): GameNoteContextService {
  return new GameNoteContextService(() => Promise.resolve(memberships), loadNpcs, gameChatRulesContextService);
}

describe('GameNoteContextService', () => {
  it('помечает ссылки игровым контекстом и ограничивает их игрой', () => {
    const context = buildService().build({
      rules: [],
      mechanics: [],
      spaceId: 3,
      spaceCode: 'razrabotka',
      rulesRevision: 4,
      gameId: 7,
    });

    expect(context.inlineContext.gameId).toBe(7);
    expect(context.inlineContext.entityContext).toBe('game');
    expect(context.inlineContext.spaceId).toBe(3);
  });

  it('не предлагает покинувших игру персонажей и ищет по имени', async () => {
    const source = sourceOf(
      buildService().build({
        rules: [],
        mechanics: [],
        spaceId: 3,
        spaceCode: 'razrabotka',
        rulesRevision: 4,
        gameId: 7,
      }).tokenSources,
      'character',
    );

    expect((await source.search('')).map((option) => option.label)).toEqual(['Бородач', 'Гаррик']);
    expect(await source.search('гарр')).toEqual([{ value: '1', label: 'Гаррик' }]);
    expect(await source.search('ушед')).toEqual([]);
  });

  it('ищет только активных НПС этой игры', async () => {
    npcQueries.length = 0;
    const source = sourceOf(
      buildService().build({
        rules: [],
        mechanics: [],
        spaceId: 3,
        spaceCode: 'razrabotka',
        rulesRevision: 4,
        gameId: 7,
      }).tokenSources,
      'npc',
    );

    const options = await source.search('торг');

    expect(npcQueries).toEqual([{ gameId: 7, status: 'active', query: 'торг', limit: 10 }]);
    expect(options).toEqual([
      { value: '11', label: 'Торговец' },
      { value: '12', label: 'Наёмник' },
    ]);
  });

  it('не ломает пикер при ошибке загрузки списков', async () => {
    const service = new GameNoteContextService(
      () => Promise.reject(new Error('network')),
      () => Promise.reject(new Error('network')),
      gameChatRulesContextService,
    );
    const context = service.build({
      rules: [],
      mechanics: [],
      spaceId: null,
      spaceCode: 'razrabotka',
      rulesRevision: null,
      gameId: 7,
    });

    expect(await sourceOf(context.tokenSources, 'character').search('')).toEqual([]);
    expect(await sourceOf(context.tokenSources, 'npc').search('')).toEqual([]);
  });
});
