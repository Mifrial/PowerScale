import { describe, expect, it } from 'vitest';
import { canViewGame, canEditGame, canModerateGame } from '@/modules/Roleplay/Game/Utils/access';
import type { User } from '@/modules/Core/User/Dto/User';
import type { Game } from '@/modules/Roleplay/Game/Dto/Game';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';
import type { GameMemberRole } from '@/modules/Roleplay/Game/Enum/GameMemberRole';

function makeGame(overrides: Partial<Game> = {}): Game {
  return {
    id: 1,
    name: 'Тестовая игра',
    shortDescription: null,
    status: 'recruiting',
    visibility: 'all',
    joinPolicy: 'anyone',
    ownerId: 7,
    ownerName: 'Владелец',
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 12,
    memberCount: 2,
    tags: [],
    ...overrides,
  };
}

function member(userId: number, role: GameMemberRole, permissions: string[] = []): GameMember {
  return { userId, userName: 'Участник', role, permissions };
}

function makeDetail(game: Game, members: GameMember[]): GameDetail {
  return {
    game,
    description: null,
    osPointsLimit: null,
    olPointsLimit: null,
    orPointsLimit: null,
    moneyLimit: null,
    forbiddenTags: [],
    members,
    discussionChatId: null,
    gameChatId: null,
  };
}

function makeUser(overrides: Partial<User> = {}): User {
  return {
    id: 10,
    name: 'Пользователь',
    login: 'user',
    email: 'user@test.com',
    groups: ['Игрок'],
    registered: '01.01.2026',
    active: true,
    ...overrides,
  };
}

describe('canViewGame', () => {
  it('отказывает без авторизованного пользователя', () => {
    expect(canViewGame(null, makeGame(), [])).toBe(false);
    expect(canViewGame(undefined, makeGame(), [])).toBe(false);
  });

  it('разрешает владельцу, включая черновик', () => {
    expect(canViewGame(makeUser({ id: 7 }), makeGame({ status: 'draft' }), [])).toBe(true);
  });

  it('черновик виден только владельцу и game.view_all', () => {
    const game = makeGame({ status: 'draft' });
    expect(canViewGame(makeUser(), game, [])).toBe(false);
    expect(canViewGame(makeUser({ permissions: ['game.view_all'] }), game, [])).toBe(true);
  });

  it('видимость all открыта не-участнику (кроме черновика)', () => {
    expect(canViewGame(makeUser(), makeGame(), [])).toBe(true);
  });

  it('видимость players требует участия', () => {
    const game = makeGame({ visibility: 'players' });
    expect(canViewGame(makeUser(), game, [])).toBe(false);
    expect(canViewGame(makeUser({ id: 10 }), game, [10])).toBe(true);
  });

  it('видимость invited требует участия', () => {
    const game = makeGame({ visibility: 'invited' });
    expect(canViewGame(makeUser(), game, [])).toBe(false);
    expect(canViewGame(makeUser({ id: 10 }), game, [10])).toBe(true);
  });

  it('super_admin видит черновик чужой игры', () => {
    expect(canViewGame(makeUser({ super_admin: true }), makeGame({ status: 'draft' }), [])).toBe(true);
  });
});

describe('canEditGame', () => {
  it('отказывает без авторизованного пользователя и не-участнику', () => {
    const detail = makeDetail(makeGame(), [member(7, 'owner')]);
    expect(canEditGame(null, detail)).toBe(false);
    expect(canEditGame(makeUser(), detail)).toBe(false);
  });

  it('разрешает владельцу и ведущему', () => {
    const owner = makeDetail(makeGame(), [member(10, 'owner')]);
    const gm = makeDetail(makeGame(), [member(10, 'gm')]);
    expect(canEditGame(makeUser(), owner)).toBe(true);
    expect(canEditGame(makeUser(), gm)).toBe(true);
  });

  it('участнику — только с индивидуальным правом game.edit', () => {
    const without = makeDetail(makeGame(), [member(10, 'player')]);
    const withPerm = makeDetail(makeGame(), [member(10, 'player', ['game.edit'])]);
    expect(canEditGame(makeUser(), without)).toBe(false);
    expect(canEditGame(makeUser(), withPerm)).toBe(true);
  });

  it('глобальное game.edit_all открывает редактирование', () => {
    const detail = makeDetail(makeGame(), [member(7, 'owner')]);
    expect(canEditGame(makeUser({ permissions: ['game.edit_all'] }), detail)).toBe(true);
  });
});

describe('canModerateGame', () => {
  it('разрешает владельцу и ведущему', () => {
    const owner = makeDetail(makeGame(), [member(10, 'owner')]);
    const gm = makeDetail(makeGame(), [member(10, 'gm')]);
    expect(canModerateGame(makeUser(), owner)).toBe(true);
    expect(canModerateGame(makeUser(), gm)).toBe(true);
  });

  it('участнику — только с индивидуальным правом game.moderate', () => {
    const without = makeDetail(makeGame(), [member(10, 'player')]);
    const withPerm = makeDetail(makeGame(), [member(10, 'player', ['game.moderate'])]);
    expect(canModerateGame(makeUser(), without)).toBe(false);
    expect(canModerateGame(makeUser(), withPerm)).toBe(true);
  });
});
