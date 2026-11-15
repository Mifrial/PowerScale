import { describe, expect, it } from 'vitest';
import type { ItemStorageValue } from '@/modules/Roleplay/Character/Dto/ItemStorageValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { magicSourcePowerService } from '@/modules/Roleplay/Character/Service/Instance/magicSourcePowerService';

const dim = (base: number, size = 0) => ({ base, size });

const rule = (code: string, type: Rule['type'], name: string, spec: Rule['spec']): Rule => ({
  id: null,
  code,
  type,
  name,
  description: '',
  spaceId: 1,
  mechanics: [],
  createdAt: 0,
  spec,
});

const rules: Rule[] = [
  rule('magic-power', 'characteristic', 'Магическая мощь', { type: 'characteristic', group: 'magic' }),
  rule('magic-crystal', 'item', 'Кристалл-хранилище', {
    category: 'equipment',
    innate: true,
    cost_gm: null,
    weight: null,
    special_rule_codes: [],
    storage: {
      volume_base: 20,
      power_parameter: 'max_power',
      volume_increment: 10,
      volume_parameter: 'volume',
      recorded_parameter: 'recorded_spells',
      min_volume_power_delta: 3,
    },
  }),
];

const source = (overrides: Partial<ItemStorageValue> = {}): ItemStorageValue => ({
  inventoryId: 3,
  ruleCode: 'magic-crystal',
  maxPower: dim(5, 1),
  volume: 160,
  occupied: 30,
  mana: 100,
  manaMax: 130,
  ...overrides,
});

describe('MagicSourcePowerService', () => {
  it('строит характеристику «Магическая мощь» из Предельной мощи кристалла', () => {
    const characteristic = magicSourcePowerService.characteristicOf(source(), rules);

    expect(characteristic.ruleCode).toBe('magic-power');
    expect(characteristic.name).toBe('Магическая мощь');
    expect(characteristic.group).toBe('magic');
    expect(characteristic.value).toEqual(dim(5, 1));
    expect(characteristic.valueLabel).toBe('5↑');
    expect(characteristic.modifiers).toEqual([]);
  });

  it('называет источник именем правила мира', () => {
    expect(magicSourcePowerService.sourceName(source(), rules)).toBe('Кристалл-хранилище');
    expect(magicSourcePowerService.sourceName(source({ ruleCode: 'unknown' }), rules)).toBe('unknown');
  });
});
