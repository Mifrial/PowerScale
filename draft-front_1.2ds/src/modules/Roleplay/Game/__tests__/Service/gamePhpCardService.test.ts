import { describe, expect, it } from 'vitest';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';
import { gamePhpCardService } from '@/modules/Roleplay/Game/Service/Instance/gamePhpCardService';

function makeData(): CreateGameData {
  return {
    name: 'Tale',
    shortDescription: 'Short',
    description: 'Long',
    spaceId: 3,
    spaceCode: 'razrabotka',
    rulesRevision: 1,
    status: 'draft',
    visibility: 'all',
    joinPolicy: 'anyone',
    osPointsLimit: 12,
    olPointsLimit: 4,
    orPointsLimit: 8,
    moneyLimit: 100,
    tags: ['noir'],
    forbiddenTags: ['техно'],
  };
}

describe('GamePhpCardService', () => {
  it('create/update не шлёт tags и всегда пишет olPointsLimit null', () => {
    const created = gamePhpCardService.createBody(makeData());
    expect(created).not.toHaveProperty('tags');
    expect(created).not.toHaveProperty('forbiddenTags');
    expect(created.olPointsLimit).toBeNull();
    expect(created.osPointsLimit).toBe(12);
    expect(created.spaceCode).toBe('razrabotka');
    const updated = gamePhpCardService.updateBody(9, makeData());
    expect(updated.id).toBe(9);
    expect(updated).not.toHaveProperty('tags');
    expect(updated.olPointsLimit).toBeNull();
  });

  it('плоская PHP-карточка даёт game.id и пустые теги', () => {
    const detail = gamePhpCardService.detailOf({
      id: 7,
      name: 'Tale',
      shortDescription: null,
      description: 'Text',
      status: 'draft',
      sessionRunning: false,
      visibility: 'all',
      joinPolicy: 'anyone',
      ownerId: 1,
      spaceId: 3,
      spaceCode: 'razrabotka',
      rulesRevision: 1,
      gameChatId: 10,
      discussionChatId: 11,
      osPointsLimit: 12,
      olPointsLimit: null,
      orPointsLimit: null,
      moneyLimit: null,
    });
    expect(detail.game.id).toBe(7);
    expect(detail.game.tags).toEqual([]);
    expect(detail.forbiddenTags).toEqual([]);
    expect(detail.members).toEqual([]);
    expect(detail.game.ownerName).toBe('');
    expect(detail.game.memberCount).toBe(0);
    expect(detail.description).toBe('Text');
    expect(detail.osPointsLimit).toBe(12);
    expect(detail.gameChatId).toBe(10);
  });

  it('список участников PHP — userId и роль, без имени', () => {
    expect(
      gamePhpCardService.membersOf([
        { userId: 8, role: 'player' },
        { userId: 3, role: 'gm' },
      ]),
    ).toEqual([
      { userId: 8, userName: '#8', role: 'player', permissions: [] },
      { userId: 3, userName: '#3', role: 'gm', permissions: [] },
    ]);
  });

  it('listItem без tags не оставляет undefined', () => {
    const [game] = gamePhpCardService.listOf([
      {
        id: 2,
        name: 'Listed',
        shortDescription: 'Hi',
        status: 'recruiting',
        sessionRunning: true,
        visibility: 'all',
        joinPolicy: 'anyone',
        ownerId: 4,
        spaceId: 3,
        spaceCode: 'razrabotka',
        rulesRevision: 2,
        gameChatId: null,
        discussionChatId: null,
      },
    ]);
    expect(game.tags).toEqual([]);
    expect(game.tags.length).toBe(0);
    expect(game.ownerName).toBe('');
    expect(game.memberCount).toBe(0);
    expect(game.sessionRunning).toBe(true);
  });
});
