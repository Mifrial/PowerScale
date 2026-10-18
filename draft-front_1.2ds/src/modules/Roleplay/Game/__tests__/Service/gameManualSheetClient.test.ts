import { describe, expect, it, vi } from 'vitest';
import { gameManualSheetClient } from '@/modules/Roleplay/Game/Service/Instance/gameManualSheetClient';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';

describe('gameManualSheetClient', () => {
  it('шлёт абсолютный current и запоминает actualVersion', async () => {
    gamePhpBattleContext.clear();
    gamePhpBattleContext.rememberSheet('character:4', 7);
    const manualSheet = vi.fn(async () => ({ actualVersion: 8 }));
    await gameManualSheetClient.apply(
      { manualSheet } as unknown as IGameApi,
      2,
      'character:4',
      'setResource',
      gameManualSheetClient.resourceDetails('ap', { base: 3, size: 0 }),
    );
    expect(manualSheet).toHaveBeenCalledWith(
      expect.objectContaining({
        operation: 'setResource',
        expectedSheetVersion: 7,
        details: { resourceCode: 'ap', current: { base: 3, size: 0 } },
        target: { kind: 'character', id: 4 },
      }),
    );
    expect(gamePhpBattleContext.sheetVersionByKey['character:4']).toBe(8);
  });

  it('patch/remove требуют id и не шлют index', () => {
    expect(() => gameManualSheetClient.removeDetails({ stateRuleCode: 'mark' })).toThrow(
      'У состояния нет id листа',
    );
    expect(gameManualSheetClient.patchDetails({ stateRuleCode: 'mark', id: 9 }, { value: 4 })).toEqual({
      stateRuleCode: 'mark',
      id: 9,
      value: 4,
    });
  });
});
