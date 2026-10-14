import { describe, expect, it } from 'vitest';
import { gameInitiativeSheetPreviewService } from '@/modules/Roleplay/Game/Service/Instance/gameInitiativeSheetPreviewService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function rule(code: string, name: string): Rule {
  return {
    id: 1,
    spaceId: 1,
    code,
    type: 'characteristic',
    name,
    description: '',
    mechanics: [],
    createdAt: 0,
  };
}

describe('GameInitiativeSheetPreviewService.viewsOf', () => {
  it('закупка с value попадает в map', () => {
    const views = gameInitiativeSheetPreviewService.viewsOf(
      {
        characteristicPurchases: [{ characteristicCode: 'awareness', cost: 2, value: { base: 1, size: 0 } }],
      },
      [rule('awareness', 'Восприятие')],
    );

    expect(views.get('awareness')).toEqual({
      code: 'awareness',
      name: 'Восприятие',
      value: { base: 1, size: 0 },
    });
  });

  it('закупка без value не попадает в map', () => {
    const views = gameInitiativeSheetPreviewService.viewsOf(
      {
        characteristicPurchases: [{ characteristicCode: 'awareness', cost: 2 }],
      },
      [rule('awareness', 'Восприятие')],
    );

    expect(views.size).toBe(0);
  });
});
