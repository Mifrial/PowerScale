import { describe, expect, it } from 'vitest';
import {
  gameDetails,
  fetchGame,
  fetchGames,
  createGame,
  updateGame,
  addGameMember,
  updatePersonalNotes,
} from '@/modules/Roleplay/Game/Mock/mockGames';
import { createInvitation, respondInvitation } from '@/modules/Roleplay/Game/Mock/mockGameInvitations';
import { mockGetChats } from '@/modules/Messages/Chat/Mock/mockChat';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import type { CreateGameData } from '@/modules/Roleplay/Game/Dto/CreateGameData';

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
    osPointsLimit: 12,
    olPointsLimit: null,
    orPointsLimit: null,
    moneyLimit: 10000,
    tags: ['эпик'],
    forbiddenTags: [],
  };
}

// Инвариант привязки игр к ревизиям правил: как у персонажей,
// spaceId 1 = razrabotka (revision ≤ 5), spaceId 2 = actual (revision ≤ 12).
function maxRevisionOf(spaceId: number): number {
  return spaceId === 1 ? 5 : 12;
}

describe('mockGames: согласованность фикстур', () => {
  it('владельцы и участники существуют в mockUsers', () => {
    for (const detail of gameDetails) {
      expect(userIds.has(detail.game.ownerId), `владелец ${detail.game.ownerId}`).toBe(true);
      for (const member of detail.members) {
        expect(userIds.has(member.userId), `участник ${member.userId}`).toBe(true);
      }
    }
  });

  it('привязка (spaceId, rulesRevision) не выходит за ревизии моков Space', () => {
    for (const detail of gameDetails) {
      expect(detail.game.rulesRevision).toBeLessThanOrEqual(maxRevisionOf(detail.game.spaceId));
    }
  });

  it('карточка списка несёт chat id без деталки', async () => {
    const list = await fetchGames();
    const forgotten = list.find((game) => game.id === 1);
    const detail = gameDetails.find((entry) => entry.game.id === 1);
    expect(forgotten?.gameChatId).toBe(detail?.gameChatId);
    expect(forgotten?.discussionChatId).toBe(detail?.discussionChatId);
    expect(forgotten?.gameChatId).not.toBeNull();
    expect(forgotten?.discussionChatId).not.toBeNull();
    expect(detail?.game.gameChatId).toBe(detail?.gameChatId);
    expect(detail?.game.discussionChatId).toBe(detail?.discussionChatId);
  });

  it('memberCount карточки совпадает со списком участников', () => {
    for (const detail of gameDetails) {
      expect(detail.game.memberCount).toBe(detail.members.length);
    }
  });

  it('роли участников валидны', () => {
    for (const detail of gameDetails) {
      for (const member of detail.members) {
        expect(['owner', 'gm', 'player']).toContain(member.role);
      }
    }
  });
});

describe('mockGames: видимость списка', () => {
  it('текущему пользователю (id 1) видны игры по статусу/видимости/участию', async () => {
    const games = await fetchGames();
    const names = games.map((game) => game.name);

    // Не видна «Городские тени» (visibility invited, пользователь не участник).
    expect(names).not.toContain('Городские тени');
    // Черновик владельца и все игры с visibility all/friends — видны.
    expect(names).toContain('Сага о северных землях');
    expect(names).toContain('Забытые земли');
  });
});

describe('mockGames: создание и чтение', () => {
  it('createGame создаёт игру владельцем-текущим пользователем и она читается', async () => {
    const created = await createGame(makeData('Новая кампания'));
    expect(created.game.ownerId).toBe(1);
    expect(created.game.memberCount).toBe(1);
    expect(created.game.rulesRevision).toBe(12);

    const fetched = await fetchGame(created.game.id);
    expect(fetched.game.name).toBe('Новая кампания');
    expect(fetched.members[0].role).toBe('owner');
  });
});

describe('mockGames: обсуждение и редактирование', () => {
  it('все игры имеют обсуждение (game_discussion chat)', () => {
    for (const detail of gameDetails) {
      expect(detail.discussionChatId, `игра ${detail.game.id}`).not.toBeNull();
    }
  });

  it('все игры имеют игровой чат (game chat)', () => {
    for (const detail of gameDetails) {
      expect(detail.gameChatId, `игра ${detail.game.id}`).not.toBeNull();
    }
  });

  it('createGame создаёт обсуждение игры', async () => {
    const created = await createGame(makeData('Создание с обсуждением'));
    expect(created.discussionChatId).not.toBeNull();
    expect(created.game.discussionChatId).toBe(created.discussionChatId);
  });

  it('createGame создаёт игровой чат игры', async () => {
    const created = await createGame(makeData('Создание с игровым чатом'));
    expect(created.gameChatId).not.toBeNull();
    expect(created.game.gameChatId).toBe(created.gameChatId);
  });

  it('updateGame меняет настройки (round-trip)', async () => {
    const created = await createGame(makeData('До редактирования'));
    const updated = await updateGame(created.game.id, makeData('После редактирования'));
    expect(updated.game.name).toBe('После редактирования');

    const fetched = await fetchGame(created.game.id);
    expect(fetched.game.name).toBe('После редактирования');
  });

  it('updateGame не снимает playing', async () => {
    const playing = gameDetails.find((detail) => detail.game.status === 'playing');
    expect(playing).toBeDefined();
    const data = {
      name: playing!.game.name,
      shortDescription: playing!.game.shortDescription,
      description: playing!.description,
      status: 'in_process' as const,
      visibility: playing!.game.visibility,
      joinPolicy: playing!.game.joinPolicy,
      spaceId: playing!.game.spaceId,
      spaceCode: playing!.game.spaceCode,
      rulesRevision: playing!.game.rulesRevision,
      osPointsLimit: playing!.osPointsLimit,
      olPointsLimit: playing!.olPointsLimit,
      orPointsLimit: playing!.orPointsLimit,
      moneyLimit: playing!.moneyLimit,
      tags: playing!.game.tags,
      forbiddenTags: playing!.forbiddenTags,
    };
    await expect(updateGame(playing!.game.id, data)).rejects.toThrow('Сначала остановите сессию');
    expect(playing!.game.status).toBe('playing');
  });

  it('роли игрового чата синхронизированы с ролями участников игры (D103)', async () => {
    const detail = await createGame(makeData('Тест-роли-чата'));
    const ownerRole = (await mockGetChats())
      .find((chat) => chat.id === detail.gameChatId)
      ?.members.find((member) => member.userId === detail.game.ownerId)?.role;
    expect(ownerRole).toBe('gm');

    await addGameMember(detail.game.id, 2, 'player');
    const playerRole = (await mockGetChats())
      .find((chat) => chat.id === detail.gameChatId)
      ?.members.find((member) => member.userId === 2)?.role;
    expect(playerRole).toBe('player');
  });

  it('принятие приглашения синкает роль участника в игровой чат (D103)', async () => {
    const detail = await createGame(makeData('Тест-роли-приглашение'));
    const invitation = await createInvitation(detail.game.id, 2);
    await respondInvitation(invitation.id, 'accept');

    const playerRole = (await mockGetChats())
      .find((chat) => chat.id === detail.gameChatId)
      ?.members.find((member) => member.userId === 2)?.role;
    expect(playerRole).toBe('player');
  });

  it('личные заметки игры видны только текущему пользователю', async () => {
    const ivan = await fetchGame(1);
    expect(ivan.personalNotes).toBe('Спросить Анну про руины на севере.');

    await mockLogin('admin', 'test');
    const adminView = await fetchGame(1);
    expect(adminView.personalNotes).toBeNull();
    await updatePersonalNotes(1, 'план ГМ');
    expect((await fetchGame(1)).personalNotes).toBe('план ГМ');
    await mockLogout();

    expect((await fetchGame(1)).personalNotes).toBe('Спросить Анну про руины на севере.');
  });
});
