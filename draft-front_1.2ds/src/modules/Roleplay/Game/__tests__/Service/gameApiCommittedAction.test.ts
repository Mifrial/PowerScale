import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';

describe('GameApi committed action debt', () => {
  const runAction = vi.fn();
  let api: GameApi;

  beforeEach(() => {
    runAction.mockReset();
    gamePhpBattleContext.clear();
    api = new GameApi({ runAction } as unknown as Engine);
  });

  it('читает слот долга боя как незавершённое действие', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    runAction.mockResolvedValue({
      success: true,
      data: {
        battleId: 9,
        characters: [],
        npcs: [],
        sheets: [],
        actionDebt: {
          'character:1': {
            remaining: 5,
            total: 10,
            kind: 'spell',
            actionRuleCode: 'magic-perception',
            payload: {},
          },
        },
      },
    });

    const sessions = await api.getCommittedActionSessions(1);

    expect(runAction.mock.calls[0]?.[0]).toBe('game.getBattleSheets');
    expect(sessions['character:1']).toMatchObject({
      actionRuleCode: 'magic-perception',
      remainingOd: 5,
      totalOd: 10,
    });
  });

  it('долг касательного каста читается как незавершённое действие', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    runAction.mockResolvedValue({
      success: true,
      data: {
        battleId: 9,
        characters: [],
        npcs: [],
        sheets: [],
        actionDebt: {
          'character:1': {
            remaining: 7,
            total: 12,
            kind: 'strike',
            actionRuleCode: 'swing',
            payload: { spellCast: { spellCode: 'heightened-senses', pathCode: 'nolomancy' } },
          },
        },
      },
    });

    const sessions = await api.getCommittedActionSessions(1);

    expect(sessions['character:1']).toMatchObject({
      actionRuleCode: 'heightened-senses',
      remainingOd: 7,
      totalOd: 12,
    });
  });

  it('обычный долг удара остаётся окну удара', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    runAction.mockResolvedValue({
      success: true,
      data: {
        battleId: 9,
        characters: [],
        npcs: [],
        sheets: [],
        actionDebt: {
          'character:1': { remaining: 3, total: 5, kind: 'strike', actionRuleCode: 'swing', payload: {} },
        },
      },
    });

    const sessions = await api.getCommittedActionSessions(1);

    expect(sessions).toEqual({});
  });

  it('без боя долгов нет', async () => {
    const sessions = await api.getCommittedActionSessions(1);

    expect(sessions).toEqual({});
    expect(runAction).not.toHaveBeenCalled();
  });

  it('продолжение долга двигает курсор шкалы', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    gamePhpBattleContext.rememberScale({
      gameId: 1,
      active: true,
      participants: [
        { id: 'character:1', name: 'Hero', kind: 'character', entityId: 1 },
        { id: 'npc:1', name: 'Foe', kind: 'npc', entityId: 1 },
      ],
      activeIndex: 0,
      round: 1,
      updatedAt: '',
    });
    runAction.mockResolvedValue({
      success: true,
      data: { battleId: 9, version: 5, activeIndex: 1, round: 2, sheetVersion: 11 },
    });

    await api.continueAction({
      gameId: 1,
      idempotencyKey: 'debt-1',
      battleId: 9,
      expectedBattleVersion: 4,
      expectedSheetVersion: 10,
    });

    expect(gamePhpBattleContext.getScale()?.activeIndex).toBe(1);
    expect(gamePhpBattleContext.getScale()?.round).toBe(2);
  });
});
