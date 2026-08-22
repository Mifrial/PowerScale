import type { GameInvitation } from '@/modules/Roleplay/Game/Dto/GameInvitation';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import { getCurrentUserId } from '@/modules/Core/Auth/Mock/mockAuth';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { gameDetails, syncGameChatRoles } from '@/modules/Roleplay/Game/Mock/mockGames';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function userName(userId: number): string {
  const user = realUsers.find((u) => u.id === userId);

  return user ? [user.name, user.surname].filter(Boolean).join(' ') || user.login : 'Неизвестно';
}

// Приглашения в игры (ТР §3 `game_invitations`). Инварианты: inviter/invitee — из mockUsers, gameId — из mockGames.
export const gameInvitations: GameInvitation[] = [
  {
    id: 1,
    gameId: 6,
    inviterId: 7,
    inviterName: 'Ольга Новикова',
    inviteeId: 1,
    inviteeName: 'Иван Петров',
    status: 'sent',
    createdAt: '2026-08-12T10:00:00',
  },
  {
    id: 2,
    gameId: 1,
    inviterId: 3,
    inviterName: 'Анна Смирнова',
    inviteeId: 10,
    inviteeName: 'Алексей Фёдоров',
    status: 'sent',
    createdAt: '2026-08-11T15:00:00',
  },
  {
    id: 3,
    gameId: 5,
    inviterId: 3,
    inviterName: 'Анна Смирнова',
    inviteeId: 1,
    inviteeName: 'Иван Петров',
    status: 'viewed',
    createdAt: '2026-08-09T12:30:00',
  },
];

let nextInvitationId = Math.max(0, ...gameInvitations.map((invitation) => invitation.id)) + 1;

export async function fetchGameInvitations(gameId: number, _signal?: AbortSignal): Promise<GameInvitation[]> {
  await delay(150);

  return gameInvitations.filter((invitation) => invitation.gameId === gameId);
}

export async function createInvitation(
  gameId: number,
  inviteeId: number,
  _signal?: AbortSignal,
): Promise<GameInvitation> {
  await delay(200);
  const inviterId = getCurrentUserId();
  const invitation: GameInvitation = {
    id: nextInvitationId++,
    gameId,
    inviterId,
    inviterName: userName(inviterId),
    inviteeId,
    inviteeName: userName(inviteeId),
    status: 'sent',
    createdAt: new Date().toISOString(),
  };
  gameInvitations.push(invitation);

  return { ...invitation };
}

/** Ответ приглашённого: accept добавляет его участником игры (role player), decline — помечает отклонённым. */
export async function respondInvitation(
  invitationId: number,
  action: 'accept' | 'decline',
  _signal?: AbortSignal,
): Promise<GameInvitation> {
  await delay(200);
  const invitation = gameInvitations.find((i) => i.id === invitationId);
  if (!invitation) throw new Error('Приглашение не найдено');
  invitation.status = action === 'accept' ? 'accepted' : 'declined';
  if (action === 'accept') {
    const detail = gameDetails.find((d) => d.game.id === invitation.gameId);
    if (detail) {
      const member: GameMember = {
        userId: invitation.inviteeId,
        userName: invitation.inviteeName,
        role: 'player',
        permissions: [],
      };
      if (!detail.members.some((m) => m.userId === member.userId)) {
        detail.members.push(member);
        detail.game.memberCount = detail.members.length;
        syncGameChatRoles(detail);
      }
    }
  }

  return { ...invitation };
}
