import { describe, expect, it } from 'vitest';
import { PhpChatRollService } from '@/modules/Roleplay/Game/Service/PhpChatRollService';

describe('PhpChatRollService', () => {
  it('переносит размерные условия дальнего боя и автора в карточку броска', () => {
    const result = new PhpChatRollService().fromPhpCheck({
      success: false,
      rating: -5,
      difficulty: { base: 3, size: 0 },
      roll: { base: 1, size: -1 },
      actorKey: 'npc:1',
      masteryAdjustments: [
        {
          source_code: 'distance',
          source_label: 'Дистанция ≥3 ипари: мастерство на размер ниже',
          delta: -1,
        },
      ],
      efficiencyAdjustments: [
        {
          source_code: 'distance',
          source_label: 'Штраф дистанции',
          delta: -2,
        },
      ],
      advantages: [{ source_code: 'flank', delta: -2 }],
      faces: [6, 5, 2],
      adjustedRolls: [6, 5, 2],
      successes: [-1, 0, 1],
      ranged_hit: {
        cover: 0.5,
        cover_value: { base: 1, size: -1 },
        defense_result: 0.5,
        defense_value: { base: 1, size: -1 },
        minimum_value: { base: 1, size: -1 },
        base_value: { base: 3, size: -1 },
        reaction: 'dodge',
        range_size: 2,
        falloff_value: { base: 10, size: 0 },
        distance_ipari: 11,
        distance_value: { base: 11, size: 0 },
        difficulty: { base: 3, size: 0 },
      },
    });

    expect(result?.spec.actorKey).toBe('npc:1');
    expect(result?.spec.masteryAdjustments).toEqual([
      {
        source_code: 'distance',
        source_label: 'Дистанция ≥3 ипари: мастерство на размер ниже',
        delta: -1,
      },
    ]);
    expect(result?.spec.efficiencyAdjustments).toEqual([
      {
        source_code: 'distance',
        source_label: 'Штраф дистанции',
        delta: -2,
      },
    ]);
    expect(result?.spec.advantages).toEqual([
      {
        source_code: 'flank',
        source_label: 'Фланговая атака',
        delta: -2,
      },
    ]);
    expect(result?.check?.ranged_hit).toEqual({
      cover: 0.5,
      cover_value: { base: 1, size: -1 },
      defense_result: 0.5,
      defense_value: { base: 1, size: -1 },
      minimum_value: { base: 1, size: -1 },
      base_value: { base: 3, size: -1 },
      reaction: 'dodge',
      range_size: 2,
      falloff_value: { base: 10, size: 0 },
      distance_ipari: 11,
      distance_value: { base: 11, size: 0 },
      difficulty: { base: 3, size: 0 },
    });
  });
});
