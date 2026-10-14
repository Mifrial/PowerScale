import { describe, expect, it } from 'vitest';
import type { GameJoinRequest } from '@/modules/Roleplay/Game/Dto/GameJoinRequest';
import { mockGetUsersByIds } from '@/modules/Core/User/Mock/mockUsers';
import { joinRequestCaptionService } from '@/modules/Roleplay/Game/Service/Instance/joinRequestCaptionService';

describe('JoinRequestCaptionService', () => {
  it('заявка без userName не падает и показывает запасной вид по id', () => {
    const request: GameJoinRequest = {
      id: 4,
      gameId: 2,
      userId: 17,
      status: 'pending',
      createdAt: '2026-10-13T12:00:00',
    };

    expect(request.userName).toBeUndefined();
    expect(joinRequestCaptionService.caption(undefined, request.userId)).toEqual({
      name: '#17',
      initials: '?',
    });
  });

  it('ответ user.getByIds рисует имя и инициал', async () => {
    const users = await mockGetUsersByIds([1]);
    const card = users.find((user) => user.id === 1);

    expect(joinRequestCaptionService.caption(card, 1)).toEqual({
      name: 'Иван Петров',
      initials: 'ИП',
    });
  });
});
