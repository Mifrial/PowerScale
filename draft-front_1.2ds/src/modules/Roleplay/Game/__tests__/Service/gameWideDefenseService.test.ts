import { describe, expect, it } from 'vitest';
import { gameWideDefenseService } from '@/modules/Roleplay/Game/Service/Instance/gameWideDefenseService';

import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GamePhpOpenWideStrike } from '@/modules/Roleplay/Game/Dto/GamePhpOpenWideStrike';

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

function wide(overrides: Partial<GamePhpOpenWideStrike> = {}): GamePhpOpenWideStrike {
  return {
    id: 7,
    attacker: { type: 'character', id: 3 },
    actionRuleCode: 'swing',
    itemInventoryId: 1,
    itemRuleCode: 'sword',
    profileType: 'strike',
    profileIndex: 0,
    targets: [
      { type: 'character', id: 5, answered: false },
      { type: 'npc', id: 9, answered: false },
    ],
    ...overrides,
  };
}

describe('GameWideDefenseService', () => {
  it('владелец отвечает только за свою цель, ведущий — за любую', () => {
    const rows = [membership()];
    const first = wide().targets[0];
    const npc = wide().targets[1];
    expect(first && gameWideDefenseService.canAnswer(first, 10, rows, false)).toBe(true);
    expect(first && gameWideDefenseService.canAnswer(first, 11, rows, false)).toBe(false);
    expect(npc && gameWideDefenseService.canAnswer(npc, 10, rows, false)).toBe(false);
    expect(npc && gameWideDefenseService.canAnswer(npc, 7, rows, true)).toBe(true);
  });

  it('владелец атакующего видит ожидание, но не отвечает за чужие цели', () => {
    const strike = wide({ attacker: { type: 'character', id: 1 } });
    expect(gameWideDefenseService.isAttackerOwner(strike, 10, [membership({ characterId: 1 })])).toBe(true);
    expect(gameWideDefenseService.shouldAutoOpen(strike, 10, [membership({ characterId: 1 })], false)).toBe(true);
    expect(gameWideDefenseService.answerableTargets(strike, 10, [membership({ characterId: 1 })], false)).toEqual([]);
  });

  it('несколько своих целей попадают в ответ, чужая — нет', () => {
    const rows = [membership(), membership({ characterId: 6 })];
    const strike = wide({
      targets: [
        { type: 'character', id: 5, answered: false },
        { type: 'character', id: 6, answered: false },
        { type: 'npc', id: 9, answered: false },
      ],
    });
    expect(gameWideDefenseService.ownTargets(strike, 10, rows)).toHaveLength(2);
    expect(gameWideDefenseService.answerableTargets(strike, 10, rows, false)).toHaveLength(2);
  });

  it('отвеченная цель не открывает окно повторно', () => {
    const rows = [membership()];
    const strike = wide({
      targets: [
        { type: 'character', id: 5, answered: true, reaction: 'ignore' },
        { type: 'npc', id: 9, answered: false },
      ],
    });
    expect(gameWideDefenseService.shouldAutoOpen(strike, 10, rows, false)).toBe(false);
    expect(gameWideDefenseService.pendingTargets(strike)).toEqual([{ type: 'npc', id: 9, answered: false }]);
    expect(gameWideDefenseService.waitingText(strike, rows, { 9: 'Страж' })).toBe('Ожидаем ответ: Страж');
  });

  it('ведущий закрывает ожидание НПС', () => {
    const strike = wide({
      targets: [{ type: 'npc', id: 9, answered: false }],
    });
    expect(gameWideDefenseService.shouldAutoOpen(strike, 7, [], true)).toBe(true);
    expect(gameWideDefenseService.answerableTargets(strike, 7, [], true)).toEqual(strike.targets);
  });
});
