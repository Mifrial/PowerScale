import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { gameDetails, syncGameChatRoles } from '@/modules/Roleplay/Game/Mock/mockGames';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function userName(userId: number): string {
  const user = realUsers.find((u) => u.id === userId);

  return user ? [user.name, user.surname].filter(Boolean).join(' ') || user.login : 'Неизвестно';
}

// Заявки на вступление в игры (join_policy «любой желающий/друзья»). Инварианты:
// gameId — из mockGames, userId — из mockUsers, у пользователя нет активной заявки/членства.
export const gameJoinRequests: GameJoinRequest[] = [
  {
    id: 1,
    gameId: 1,
    userId: 8,
    userName: 'Сергей Лебедев',
    status: 'pending',
    createdAt: '2026-08-12T09:00:00',
  },
  {
    id: 2,
    gameId: 2,
    userId: 1,
    userName: 'Иван Петров',
    status: 'pending',
    createdAt: '2026-08-13T10:00:00',
  },
];

let nextJoinRequestId = Math.max(0, ...gameJoinRequests.map((request) => request.id)) + 1;

export async function fetchJoinRequests(gameId: number, _signal?: AbortSignal): Promise<GameJoinRequest[]> {
  await delay(150);

  return gameJoinRequests.filter((request) => request.gameId === gameId);
}

/** Заявка на вступление: политика игры, отсутствие членства/активной заявки текущего пользователя. */
export async function requestJoinGame(gameId: number, _signal?: AbortSignal): Promise<GameJoinRequest> {
  await delay(200);
  const detail = gameDetails.find((d) => d.game.id === gameId);
  if (!detail) throw new Error('Игра не найдена');
  const userId = getCurrentUserId();
  if (detail.game.ownerId === userId) throw new Error('Вы владелец игры');
  if (detail.members.some((member) => member.userId === userId)) throw new Error('Вы уже участник игры');
  if (detail.game.joinPolicy === 'invite_only' || detail.game.joinPolicy === 'whitelist') {
    throw new Error('Эта игра вступает только по приглашению');
  }
  if (
    gameJoinRequests.some(
      (request) => request.gameId === gameId && request.userId === userId && request.status === 'pending',
    )
  ) {
    throw new Error('Заявка уже подана на рассмотрение');
  }
  const request: GameJoinRequest = {
    id: nextJoinRequestId++,
    gameId,
    userId,
    userName: userName(userId),
    status: 'pending',
    createdAt: new Date().toISOString(),
  };
  gameJoinRequests.push(request);

  return { ...request };
}

/** Решение ведущего по заявке: accept добавляет участником (role player), decline — отклоняет. */
export async function respondJoinRequest(
  gameId: number,
  userId: number,
  action: 'accept' | 'decline',
  _signal?: AbortSignal,
): Promise<GameJoinRequest> {
  await delay(200);
  const request = gameJoinRequests.find((r) => r.gameId === gameId && r.userId === userId && r.status === 'pending');
  if (!request) throw new Error('Активная заявка не найдена');
  request.status = action === 'accept' ? 'accepted' : 'declined';
  if (action === 'accept') {
    const detail = gameDetails.find((d) => d.game.id === gameId);
    if (detail && !detail.members.some((member) => member.userId === userId)) {
      detail.members.push({ userId, userName: request.userName, role: 'player', permissions: [] });
      detail.game.memberCount = detail.members.length;
      syncGameChatRoles(detail);
    }
  }

  return { ...request };
}
