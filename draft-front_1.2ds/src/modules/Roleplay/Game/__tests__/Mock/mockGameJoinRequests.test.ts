import { describe, expect, it } from 'vitest';
import {
  gameJoinRequests,
  fetchJoinRequests,
  requestJoinGame,
  respondJoinRequest,
} from '@/modules/Roleplay/Game/Mock/mockGameJoinRequests';
import { gameDetails, createGame } from '@/modules/Roleplay/Game/Mock/mockGames';
import { mockGetChats } from '@/modules/Messages/Chat/Mock/mockChat';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';

const gameIds = new Set(gameDetails.map((detail) => detail.game.id));
const userIds = new Set(realUsers.map((user) => user.id));

function makeData(name: string): CreateGameData {
  return {
    name,
    shortDescription: null,
    description: null,
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    status: 'draft',
    visibility: 'all',
    joinPolicy: 'anyone',
    osPointsLimit: null,
    olPointsLimit: null,
    orPointsLimit: null,
    moneyLimit: null,
    tags: [],
    forbiddenTags: [],
  };
}

/** Игра «Подземелье дракона» (policy friends): пользователь 1 не участник, может подать заявку. */
function requestOnGame3(): Promise<GameJoinRequest> {
  return requestJoinGame(3);
}

describe('mockGameJoinRequests: согласованность фикстур', () => {
  it('gameId и userId существуют в моках; pending-заявки уникальны', () => {
    const seen = new Set<string>();
    for (const request of gameJoinRequests) {
      expect(gameIds.has(request.gameId), `gameId ${request.gameId}`).toBe(true);
      expect(userIds.has(request.userId), `userId ${request.userId}`).toBe(true);
      if (request.status === 'pending') {
        const key = `${request.gameId}:${request.userId}`;
        expect(seen.has(key)).toBe(false);
        seen.add(key);
      }
    }
  });

  it('fetchJoinRequests возвращает заявки только нужной игры', async () => {
    const game1 = await fetchJoinRequests(1);
    expect(game1.every((request) => request.gameId === 1)).toBe(true);
  });
});

describe('mockGameJoinRequests: подача заявки', () => {
  it('requestJoinGame создаёт pending-заявку (политика friends, не участник)', async () => {
    const request = await requestOnGame3();
    expect(request.gameId).toBe(3);
    expect(request.userId).toBe(1);
    expect(request.status).toBe('pending');
    await respondJoinRequest(3, request.userId, 'decline');
  });

  it('заявку нельзя подать участнику или владельцу игры', async () => {
    await expect(requestJoinGame(1)).rejects.toThrow('уже участник');
  });

  it('заявку нельзя подать в игру по приглашению', async () => {
    await expect(requestJoinGame(2)).rejects.toThrow('только по приглашению');
  });

  it('повторная заявка на рассмотрении запрещена', async () => {
    const request = await requestOnGame3();
    await expect(requestJoinGame(3)).rejects.toThrow('уже подана');
    await respondJoinRequest(3, request.userId, 'decline');
  });
});

describe('mockGameJoinRequests: решение ведущего', () => {
  it('accept синкает роль участника в игровой чат (D103)', async () => {
    const detail = await createGame(makeData('Тест-заявка-синк'));
    gameJoinRequests.push({
      id: 9999,
      gameId: detail.game.id,
      userId: 2,
      userName: 'Пётр Козлов',
      status: 'pending',
      createdAt: new Date().toISOString(),
    });
    await respondJoinRequest(detail.game.id, 2, 'accept');

    const role = (await mockGetChats())
      .find((chat) => chat.id === detail.gameChatId)
      ?.members.find((member) => member.userId === 2)?.role;
    expect(role).toBe('player');
  });

  it('decline не добавляет участником', async () => {
    const request = await requestOnGame3();
    const resolved = await respondJoinRequest(3, request.userId, 'decline');
    expect(resolved.status).toBe('declined');
    const detail = gameDetails.find((d) => d.game.id === 3);
    expect(detail?.members.some((member) => member.userId === request.userId)).toBe(false);
  });

  it('accept добавляет участником и меняет статус', async () => {
    const request = await requestOnGame3();
    const resolved = await respondJoinRequest(3, request.userId, 'accept');
    expect(resolved.status).toBe('accepted');
    const detail = gameDetails.find((d) => d.game.id === 3);
    expect(detail?.members.some((member) => member.userId === request.userId)).toBe(true);
  });

  it('решение по не-активной заявке запрещено', async () => {
    await expect(respondJoinRequest(1, 1, 'accept')).rejects.toThrow('Активная заявка');
  });
});
