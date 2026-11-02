import { ProcessSessionSourceService } from '@/modules/Roleplay/Game/Service/ProcessSessionSourceService';
import { describe, expect, it } from 'vitest';

describe('ProcessSessionSourceService', () => {
  it('переносит серверную сессию в клиентский Dto', () => {
    const sessions = new ProcessSessionSourceService().fromBattleSheets(7, {
      'character:3': {
        processId: 11,
        processRuleCode: 'seriya-udarov',
        currentStepCode: 'part-1',
        stepStatus: 'completed',
        weaponUseCounts: { 'id:5:0': 1 },
        lastTargetType: 'npc',
        lastTargetId: 9,
      },
    });

    expect(sessions['character:3']).toMatchObject({
      gameId: 7,
      entityKey: 'character:3',
      processRuleCode: 'seriya-udarov',
      currentStepCode: 'part-1',
      currentStepStatus: 'completed',
      status: 'active',
      weaponUseCounts: { 'id:5:0': 1 },
      lastStrikeTargetKey: 'npc:9',
    });
  });

  it('пропускает строку без текущего шага', () => {
    const sessions = new ProcessSessionSourceService().fromBattleSheets(1, {
      'character:2': {
        processId: 4,
        processRuleCode: 'seriya-udarov',
        currentStepCode: null,
        stepStatus: 'pending',
      },
    });

    expect(sessions).toEqual({});
  });
});
