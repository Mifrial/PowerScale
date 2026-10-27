import { describe, expect, it } from 'vitest';
import { wideStrikeRows } from '@/modules/Roleplay/Game/Utils/wideStrikeRows';

describe('wideStrikeRows', () => {
  it('кладет условия на строку удара, а в target оставляет только пару', () => {
    const rows = wideStrikeRows(
      [
        { type: 'character', id: 7, conditions: { flank: true } },
        { type: 'npc', id: 8 },
      ],
      { itemInventoryId: 1, itemRuleCode: 'sword', profileType: 'strike', profileIndex: 0 },
    );

    expect(rows[0].target).toEqual({ type: 'character', id: 7 });
    expect(rows[0].conditions).toEqual({ flank: true });
    expect(rows[0].instanceIndex).toBe(0);
    expect(rows[1].target).toEqual({ type: 'npc', id: 8 });
    expect(rows[1]).not.toHaveProperty('conditions');
  });

  it('не добавляет боеприпас, если он не выбран', () => {
    const [row] = wideStrikeRows(
      [{ type: 'character', id: 7 }],
      {
        itemInventoryId: 1,
        itemRuleCode: 'sword',
        profileType: 'strike',
        profileIndex: 0,
        ammunitionInventoryId: undefined,
      },
    );

    expect(row).not.toHaveProperty('ammunitionInventoryId');
  });
});
