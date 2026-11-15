import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { GamePhpActiveSpellResult } from '@/modules/Roleplay/Game/Dto/GamePhpActiveSpellResult';
import type { GamePhpDropSpellResult } from '@/modules/Roleplay/Game/Dto/GamePhpDropSpellResult';

/** Строка поддержания с серверным id и версией. */
function activeSpell(): ActiveSpell {
  return {
    id: '7',
    gameId: 1,
    casterKey: 'character:1',
    spellCode: 'lightning-generator',
    sourceKey: 'characteristic:magic-power',
    pathCode: 'arcanist',
    durationType: 'sustained',
    usedPower: { base: 4, size: 0 },
    sustainPower: { base: 4, size: 0 },
    parameterValues: { x: { base: 4, size: 0 } },
    appliedUpgradeCodes: [],
    startedRound: 1,
    startedParticipantId: 'character:1',
    version: 1,
    casterSheetVersion: 10,
  };
}

describe('GameApi active spells', () => {
  const runAction = vi.fn();
  let api: GameApi;

  beforeEach(() => {
    runAction.mockReset();
    gamePhpBattleContext.clear();
    api = new GameApi({ runAction } as unknown as Engine);
  });

  it('читает активные заклинания игры', async () => {
    runAction.mockResolvedValue({ success: true, data: [activeSpell()] });

    const rows = await api.getActiveSpells(1);

    expect(rows).toHaveLength(1);
    expect(runAction.mock.calls[0]?.[0]).toBe('game.getActiveSpells');
    expect(runAction.mock.calls[0]?.[1]).toEqual({ gameId: 1 });
  });

  it('продолжает поддержание и обновляет версии боя и листа', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    const result: GamePhpActiveSpellResult = {
      spell: { ...activeSpell(), sustainPower: { base: 5, size: 0 }, version: 2 },
      actionCost: 1,
      battleId: 9,
      version: 4,
      sheetVersion: 11,
    };
    runAction.mockResolvedValue({ success: true, data: result });

    const body = {
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'sustain-1',
      expectedBattleVersion: 4,
      expectedSheetVersion: 10,
      spellId: '7',
      sustainPower: { base: 5, size: 0 },
      expectedSpellVersion: 1,
    };
    const saved = await api.sustainActiveSpell(body);

    expect(saved.spell?.version).toBe(2);
    expect(runAction.mock.calls[0]?.[0]).toBe('game.sustainSpell');
    expect(runAction.mock.calls[0]?.[1]).toEqual(body);
    expect(gamePhpBattleContext.battleVersion).toBe(4);
    expect(gamePhpBattleContext.sheetVersionByKey['character:1']).toBe(11);
  });

  it('прекращает поддержание и обновляет версию боя', async () => {
    gamePhpBattleContext.rememberBattle(9, 4);
    const result: GamePhpDropSpellResult = { dropped: true, battleId: 9, version: 4 };
    runAction.mockResolvedValue({ success: true, data: result });

    const body = {
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'drop-1',
      expectedBattleVersion: 4,
      expectedSheetVersion: 10,
      spellId: '7',
      expectedSpellVersion: 2,
    };
    const saved = await api.dropActiveSpell(body);

    expect(saved.dropped).toBe(true);
    expect(runAction.mock.calls[0]?.[0]).toBe('game.dropSpell');
    expect(runAction.mock.calls[0]?.[1]).toEqual(body);
    expect(gamePhpBattleContext.battleVersion).toBe(4);
  });

  it('передаёт строку заряда в контекст каста', async () => {
    runAction.mockResolvedValue({
      success: true,
      data: {
        version: 2,
        caster: { type: 'character', id: 1, sheetVersion: 11 },
        pendingSpellCast: null,
      },
    });

    await api.castSpell({
      gameId: 1,
      battleId: 9,
      idempotencyKey: 'spark-1',
      expectedVersion: 1,
      expectedSheetVersion: 10,
      actor: { type: 'character', id: 1 },
      spellCode: 'spark',
      pathCode: 'arcanist',
      parameters: {},
      usedPower: {
        source: { kind: 'characteristic', code: 'magic-power' },
        value: { base: 4, size: 0 },
      },
      chargeSpellId: '7',
    });

    const payload = runAction.mock.calls[0]?.[1] as {
      action: { context: Record<string, unknown> };
    };
    expect(payload.action.context.chargeSpellId).toBe('7');
  });
});
