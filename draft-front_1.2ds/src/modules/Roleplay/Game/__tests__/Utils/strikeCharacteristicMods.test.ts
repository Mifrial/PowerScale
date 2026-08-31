import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { applyStrikeMastery, strikeCharacteristicMods } from '@/modules/Roleplay/Game/Utils/strikeCharacteristicMods';

function overview(dexSize: number, percSize: number): CharacterOverview {
  return {
    characteristics: [
      { ruleCode: 'dexterity', value: { base: 4, size: dexSize } },
      { ruleCode: 'perception', value: { base: 4, size: percSize } },
    ],
  } as unknown as CharacterOverview;
}

const rules: Rule[] = [
  { id: null, code: 'dexterity', type: 'characteristic', name: 'Ловкость' } as Rule,
  { id: null, code: 'perception', type: 'characteristic', name: 'Восприятие' } as Rule,
];

describe('strikeCharacteristicMods', () => {
  it('ниже среднего — сдвиг мастерства, не помеха к пулу', () => {
    expect(strikeCharacteristicMods(overview(-1, 0), rules)).toEqual({ masteryDelta: -1, advantages: [] });
    expect(applyStrikeMastery({ base: 5, size: -1 }, -1)).toEqual({ base: 4, size: -1 });
    expect(applyStrikeMastery({ base: 5, size: -1 }, -2)).toEqual({ base: 3, size: -1 });
  });

  it('лучшая выше среднего — + к мастерству', () => {
    expect(strikeCharacteristicMods(overview(1, 0), rules).masteryDelta).toBe(1);
    expect(applyStrikeMastery({ base: 4, size: -1 }, 1)).toEqual({ base: 5, size: -1 });
  });

  it('обе выше среднего — плюс к мастерству и преимущества от состояния', () => {
    const both = strikeCharacteristicMods(overview(1, 1), rules);
    expect(both.masteryDelta).toBe(1);
    expect(both.advantages).toEqual([{ source_code: 'state', source_label: 'Ловкость/Восприятие', delta: 2 }]);
  });
});
