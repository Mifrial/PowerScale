import { describe, expect, it } from 'vitest';
import { blockHitService } from '@/modules/Roleplay/Game/Service/Instance/blockHitService';

describe('BlockHitService', () => {
  it('провал блока оставляет РУ, успех и 0 РУ дают попадание с 1 РУ', () => {
    expect(blockHitService.attackSr('block', 3)).toBe(3);
    expect(blockHitService.attackSr('block', 0)).toBe(1);
    expect(blockHitService.attackSr('block', -1)).toBe(1);
    expect(blockHitService.attackSr('dodge', -1)).toBe(0);
  });

  it('успешный блок добавляет защиту и сопротивления предмета', () => {
    const next = blockHitService.withBlockLayers(
      { armor: [], constantDefense: 0, tiers: [], shield: null },
      {
        itemRuleCode: 'classic-shield',
        itemName: 'Классический щит',
        efficiency: { base: 5, size: 0 },
        defense: { base: 6, size: 0 },
        resistances: [
          { damage_type_code: 'slashing', value: { base: 2, size: 0 }, durability: 4, source_code: 'shield' },
        ],
      },
    );
    expect(next?.armor[0]?.lines).toEqual(
      expect.arrayContaining([
        expect.objectContaining({ kind: 'defense', value: 6 }),
        expect.objectContaining({ kind: 'resistance', value: 2, damageTypeCode: 'slashing' }),
      ]),
    );
  });
});
