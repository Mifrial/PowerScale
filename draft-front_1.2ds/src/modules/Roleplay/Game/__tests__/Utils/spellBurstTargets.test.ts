import { describe, expect, it } from 'vitest';
import { toBurstTargetRequest } from '@/modules/Roleplay/Game/Utils/spellBurstTargets';

describe('toBurstTargetRequest', () => {
  it('переводит кастера и отмеченную цель в плоские стороны', () => {
    expect(
      toBurstTargetRequest([
        { key: 'npc:3', distanceIpari: 0 },
        { key: 'npc:2', distanceIpari: 2 },
      ]),
    ).toEqual([
      { type: 'npc', id: 3, distanceIpari: 0 },
      { type: 'npc', id: 2, distanceIpari: 2 },
    ]);
  });

  it('отбрасывает мусорные ключи и отрицательную дистанцию', () => {
    expect(
      toBurstTargetRequest([
        { key: 'npc:0', distanceIpari: 1 },
        { key: 'dragon:7', distanceIpari: 1 },
        { key: 'character:5', distanceIpari: -3 },
      ]),
    ).toEqual([{ type: 'character', id: 5, distanceIpari: 0 }]);
  });
});
