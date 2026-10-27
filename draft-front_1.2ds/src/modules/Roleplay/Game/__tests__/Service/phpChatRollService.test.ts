import { describe, expect, it } from 'vitest';
import { PhpChatRollService } from '@/modules/Roleplay/Game/Service/PhpChatRollService';

describe('PhpChatRollService', () => {
  it('переносит размерные условия дальнего боя в карточку броска', () => {
    const result = new PhpChatRollService().fromPhpCheck({
      success: true,
      rating: 2,
      difficulty: { base: 4, size: -1 },
      roll: { base: 2, size: 0 },
      faces: [6, 5],
      adjustedRolls: [6, 5],
      successes: [1, 1],
      ranged_hit: {
        cover: 0.5,
        cover_value: { base: 1, size: -1 },
        defense_result: 0,
        reaction: 'ignore',
        range_size: 3,
        distance_ipari: 11,
        distance_value: { base: 11, size: 0 },
        difficulty: { base: 4, size: -1 },
      },
    });

    expect(result?.check?.ranged_hit).toEqual({
      cover: 0.5,
      cover_value: { base: 1, size: -1 },
      defense_result: 0,
      reaction: 'ignore',
      range_size: 3,
      distance_ipari: 11,
      distance_value: { base: 11, size: 0 },
      difficulty: { base: 4, size: -1 },
    });
  });
});
