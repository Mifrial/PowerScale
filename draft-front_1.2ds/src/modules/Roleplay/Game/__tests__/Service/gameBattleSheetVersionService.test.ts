import { describe, expect, it } from 'vitest';
import { gameBattleSheetVersionService } from '@/modules/Roleplay/Game/Service/Instance/gameBattleSheetVersionService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import type { GameBattleSheet } from '@/modules/Roleplay/Game/Dto/GameBattleSheet';

function sheet(states: unknown[]): GameBattleSheet {
  return {
    type: 'character',
    id: 4,
    name: 'Герой',
    actualVersion: 2,
    choices: {},
    sheet: { states },
  };
}

describe('GameBattleSheetVersionService', () => {
  it('складывает PHP sidecar раны в wound и видит зажим {kind,id}', () => {
    const version = gameBattleSheetVersionService.toVersion(
      sheet([
        {
          id: 11,
          stateRuleCode: 'wound',
          value: 3,
          bandage: 1,
          clotting: 0,
          internal: false,
          aided: false,
          heldBy: { kind: 'character', id: 4 },
        },
      ]),
    );
    const state = version.states[0];
    expect(state?.id).toBe(11);
    expect(state?.wound).toEqual({
      bandage: 1,
      clotting: 0,
      internal: false,
      aided: false,
      heldBy: { kind: 'character', id: 4 },
    });
    expect(woundInstanceService.isHeld(state!)).toBe(true);
  });
});
