import { describe, expect, it } from 'vitest';
import { gameStrikeDefenseService } from '@/modules/Roleplay/Game/Service/Instance/gameStrikeDefenseService';

import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenStrike';

function membership(overrides: Partial<GameCharacterMembership> = {}): GameCharacterMembership {
  return {
    gameId: 1,
    characterId: 5,
    characterName: 'Герой',
    characterOwnerId: 10,
    characterOwnerName: '',
    role: 'player',
    membershipStatus: 'active',
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
    ...overrides,
  };
}

function strike(overrides: Partial<GamePhpOpenStrike> = {}): GamePhpOpenStrike {
  return {
    attackerKind: 'character',
    attackerId: 3,
    defenderKind: 'character',
    defenderId: 5,
    actionRuleCode: 'swing',
    itemInventoryId: 1,
    itemRuleCode: 'sword',
    profileType: 'strike',
    profileIndex: 0,
    ...overrides,
  };
}

describe('GameStrikeDefenseService', () => {
  it('владелец защитника закрывает удар, посторонний — нет', () => {
    const rows = [membership()];
    expect(gameStrikeDefenseService.canResolve(strike(), 10, rows, false, false)).toBe(true);
    expect(gameStrikeDefenseService.canResolve(strike(), 11, rows, false, false)).toBe(false);
  });

  it('ведущий по умолчанию ждёт персонажа и закрывает только после override', () => {
    const rows = [membership()];
    expect(gameStrikeDefenseService.canResolve(strike(), 7, rows, true, false)).toBe(false);
    expect(gameStrikeDefenseService.canResolve(strike(), 7, rows, true, true)).toBe(true);
    expect(gameStrikeDefenseService.shouldAutoOpen(strike(), 7, rows, true)).toBe(true);
  });

  it('НПС-защиту ведущий закрывает сразу, игрок — нет', () => {
    const npcStrike = strike({ defenderKind: 'npc', defenderId: 9 });
    expect(gameStrikeDefenseService.canResolve(npcStrike, 7, [], true, false)).toBe(true);
    expect(gameStrikeDefenseService.canResolve(npcStrike, 10, [], false, false)).toBe(false);
  });

  it('чужой игрок не получает авто-открытие', () => {
    const rows = [membership()];
    expect(gameStrikeDefenseService.shouldAutoOpen(strike(), 11, rows, false)).toBe(false);
    expect(gameStrikeDefenseService.shouldAutoOpen(strike(), 10, rows, false)).toBe(true);
  });

  it('подпись ожидания различает персонажа и НПС', () => {
    const rows = [membership()];
    expect(gameStrikeDefenseService.waitingText(strike(), rows, { 10: 'Аня' })).toBe(
      'Ожидаем реакцию Аня за Герой',
    );
    expect(gameStrikeDefenseService.waitingText(strike(), rows, {})).toBe('Ожидаем реакцию #10 за Герой');
    expect(
      gameStrikeDefenseService.waitingText(strike({ defenderKind: 'npc', defenderId: 9 }), rows, {}, { 9: 'Страж' }),
    ).toBe('Ожидаем реакцию ведущего за Страж');
  });

  it('без имени игрока остаётся #id', () => {
    expect(gameStrikeDefenseService.waitingText(strike(), [membership({ characterOwnerId: 12 })], {})).toBe(
      'Ожидаем реакцию #12 за Герой',
    );
  });
});
