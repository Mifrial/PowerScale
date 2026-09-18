import { describe, expect, it } from 'vitest';
import type { CharacterOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacterOverview';
import { bestCombatMastery } from '@/modules/Roleplay/Game/Utils/strikeCharacteristicMods';

describe('bestCombatMastery', () => {
  it('берёт лучший тайл ББ', () => {
    const overview = {
      combat: {
        melee: {
          stat: { name: 'Общее', value: { base: 4, size: -1 } },
          weapons: [{ name: 'Коса', shortName: 'Коса', value: { base: 4, size: 0 } }],
        },
        ranged: null,
      },
    } as unknown as CharacterOverview;
    expect(bestCombatMastery(overview, false)).toEqual({ base: 4, size: 0 });
  });
});
