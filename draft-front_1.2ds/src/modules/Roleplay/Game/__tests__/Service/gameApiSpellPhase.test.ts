import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import type { GamePhpSpellCastResult } from '@/modules/Roleplay/Game/Dto/GamePhpSpellCastResult';
import type { GamePhpRunActionResult } from '@/modules/Roleplay/Game/Dto/GamePhpRunActionResult';

function spellResult(phase: 'check' | 'resolve', version: number): GamePhpSpellCastResult {
  return {
    phase,
    castId: 'cast-1',
    version,
    cast: {
      difficulty: { base: 3, size: 0 },
      needsCheck: true,
      roll: { success: true, rating: 4 },
      castOk: true,
      upgrades: [],
    },
    saturation:
      phase === 'check'
        ? {
            sourceCode: 'dynamic-energy-saturation',
            minRating: 2,
            ratingPerStep: 2,
            powerPerStep: 1,
            rating: 4,
            maxSteps: 2,
          }
        : { sourceCode: 'dynamic-energy-saturation', steps: 1, ratingSpent: 2, powerDelta: 1, remainingRating: 2 },
    nextCast: [],
    spent: 4,
    caster: { type: 'character', id: 1, sheetVersion: 10 },
    target: null,
    autoFail: false,
    spellSr: null,
    spellDamage: null,
    injury: null,
    consequence: {},
  };
}

describe('GameApi spell phases', () => {
  const runAction = vi.fn();
  let api: GameApi;

  beforeEach(() => {
    runAction.mockReset();
    gamePhpBattleContext.clear();
    api = new GameApi({ runAction } as unknown as Engine);
  });

  it('отправляет check и обновляет версию боя', async () => {
    runAction.mockResolvedValue({ success: true, data: spellResult('check', 7) });

    const result = await api.castSpell({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'cast-1',
      expectedVersion: 6,
      expectedSheetVersion: 10,
      actor: { type: 'character', id: 1 },
      spellCode: 'lightning-strike',
      pathCode: 'arcanist',
      parameters: { x: { base: 4, size: 0 } },
      usedPower: {
        source: { kind: 'characteristic', code: 'magic-power' },
        value: { base: 5, size: 1 },
      },
    });

    expect(result.phase).toBe('check');
    expect(runAction.mock.calls[0]?.[1]).toMatchObject({
      action: {
        phase: 'check',
        kind: 'spell',
        code: 'lightning-strike',
      },
    });
    expect(gamePhpBattleContext.battleVersion).toBe(7);
    expect(gamePhpBattleContext.battleId).toBe(9);
  });

  it('resolve обновляет версию и очищает отложенный каст', async () => {
    gamePhpBattleContext.rememberPendingSpellCast({
      castId: 'cast-1',
      actionId: 1,
      actor: { type: 'character', id: 1 },
      userId: 1,
      spellCode: 'lightning-strike',
      pathCode: 'arcanist',
      check: { difficulty: { base: 3, size: 0 }, needsCheck: true, roll: null, castOk: true },
      trainingDelta: 0,
      cost: 4,
      casterVersion: 10,
      target: null,
      touch: false,
      strikeId: null,
      saturation: null,
      createdAt: 1,
    });
    runAction.mockResolvedValue({ success: true, data: spellResult('resolve', 8) });

    await api.resolveSpellCast({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'cast-1:resolve',
      expectedVersion: 7,
      castId: 'cast-1',
      saturationSteps: 1,
    });

    expect(runAction.mock.calls[0]?.[1]).toMatchObject({
      action: {
        phase: 'resolve',
        kind: 'spell',
        context: { castId: 'cast-1', saturationSteps: 1 },
      },
    });
    expect(gamePhpBattleContext.battleVersion).toBe(8);
    expect(gamePhpBattleContext.pendingSpellCast.value).toBeNull();
  });

  it('resolve отправляет цели взрыва отклонения', async () => {
    runAction.mockResolvedValue({ success: true, data: spellResult('resolve', 8) });

    await api.resolveSpellCast({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'cast-1:resolve',
      expectedVersion: 7,
      castId: 'cast-1',
      saturationSteps: 0,
      burstTargets: [
        { type: 'character', id: 1, distanceIpari: 0 },
        { type: 'npc', id: 2, distanceIpari: 1 },
      ],
    });

    expect(runAction.mock.calls[0]?.[1]).toMatchObject({
      action: {
        phase: 'resolve',
        kind: 'spell',
        context: {
          castId: 'cast-1',
          saturationSteps: 0,
          burstTargets: [
            { type: 'character', id: 1, distanceIpari: 0 },
            { type: 'npc', id: 2, distanceIpari: 1 },
          ],
        },
      },
    });
    expect(gamePhpBattleContext.battleVersion).toBe(8);
  });

  it('abort обновляет версию и очищает отложенный каст', async () => {
    gamePhpBattleContext.rememberPendingSpellCast({
      castId: 'cast-1',
      actionId: 1,
      actor: { type: 'character', id: 1 },
      userId: 1,
      spellCode: 'lightning-strike',
      pathCode: 'arcanist',
      check: { difficulty: { base: 3, size: 0 }, needsCheck: true, roll: null, castOk: true },
      trainingDelta: 0,
      cost: 4,
      casterVersion: 10,
      target: null,
      touch: false,
      strikeId: null,
      saturation: null,
      createdAt: 1,
    });
    runAction.mockResolvedValue({
      success: true,
      data: {
        kind: 'spell',
        phase: 'abort',
        battleId: 9,
        version: 9,
        castId: 'cast-1',
      } satisfies GamePhpRunActionResult,
    });

    await api.abortSpellCast({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'cast-1:abort',
      expectedVersion: 8,
      castId: 'cast-1',
    });

    expect(runAction.mock.calls[0]?.[1]).toMatchObject({
      action: { phase: 'abort', kind: 'spell', context: { castId: 'cast-1' } },
    });
    expect(gamePhpBattleContext.battleVersion).toBe(9);
    expect(gamePhpBattleContext.pendingSpellCast.value).toBeNull();
  });

  it('запоминает отложенный каст из закрытия касания', async () => {
    const pending = {
      castId: 'spell-5',
      actionId: 1,
      actor: { type: 'character', id: 1 },
      userId: 1,
      spellCode: 'discharge',
      pathCode: 'arcanist',
      check: { difficulty: { base: 3, size: 0 }, needsCheck: true, roll: null, castOk: true },
      trainingDelta: 0,
      cost: 4,
      casterVersion: 10,
      target: { type: 'npc', id: 2, version: 3 },
      touch: true,
      strikeId: 5,
      saturation: {
        sourceCode: 'dynamic-energy-saturation',
        minRating: 2,
        ratingPerStep: 2,
        powerPerStep: 1,
        rating: 4,
        maxSteps: 2,
      },
      createdAt: 1,
    };
    runAction.mockResolvedValue({
      success: true,
      data: { kind: 'strike', phase: 'respond', battleId: 9, version: 5, pendingSpellCast: pending },
    });

    await api.runAction({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'touch-d',
      expectedVersion: 4,
      action: { phase: 'respond', kind: 'strike', context: {} },
    });

    expect(gamePhpBattleContext.pendingSpellCast.value).toEqual(pending);
    expect(gamePhpBattleContext.battleVersion).toBe(5);
  });
});
