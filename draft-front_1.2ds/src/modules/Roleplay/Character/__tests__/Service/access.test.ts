import { describe, expect, it } from 'vitest';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import { SHEET_VISIBILITY_DEFAULT } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_VISIBILITY_PRESETS';
import type { User } from '@/modules/Core/User/Dto/User';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';

function makeCharacter(ownerId: number): Character {
  return {
    id: 1,
    name: 'Тестовый',
    status: 'ready',
    active: true,
    ownerId,
    ownerName: 'Владелец',
    raceId: null,
    raceLabel: null,
    gameId: null,
    gameName: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 1,
    shortDescription: null,
    currentPoints: { os: 0, ol: 0, or: 0 },
    visibility: SHEET_VISIBILITY_DEFAULT,
    discussionChatId: null,
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

describe('canViewCharacter', () => {
  it('отказывает без авторизованного пользователя', () => {
    expect(characterAccessService.canViewCharacter(null, makeCharacter(10))).toBe(false);
    expect(characterAccessService.canViewCharacter(undefined, makeCharacter(10))).toBe(false);
  });

  it('разрешает владельцу без глобального права', () => {
    expect(characterAccessService.canViewCharacter(makeUser({ id: 7 }), makeCharacter(7))).toBe(true);
  });

  it('разрешает чужому с правом character.view', () => {
    const user = makeUser({ permissions: ['character.view'] });

    expect(characterAccessService.canViewCharacter(user, makeCharacter(7))).toBe(true);
  });

  it('отказывает чужому без права character.view', () => {
    expect(characterAccessService.canViewCharacter(makeUser(), makeCharacter(7))).toBe(false);
  });

  it('super_admin обходит проверку для чужого персонажа', () => {
    expect(characterAccessService.canViewCharacter(makeUser({ super_admin: true }), makeCharacter(7))).toBe(true);
  });
});

describe('canEditCharacter', () => {
  it('разрешает только владельцу', () => {
    expect(characterAccessService.canEditCharacter(makeUser({ id: 7 }), makeCharacter(7))).toBe(true);
    expect(characterAccessService.canEditCharacter(makeUser({ id: 8 }), makeCharacter(7))).toBe(false);
  });

  it('отказывает без авторизованного пользователя', () => {
    expect(characterAccessService.canEditCharacter(null, makeCharacter(7))).toBe(false);
  });

  it('владелец редактирует даже без глобального права', () => {
    const user = makeUser({ id: 7, groups: [], permissions: [] });

    expect(characterAccessService.canEditCharacter(user, makeCharacter(7))).toBe(true);
  });
});
