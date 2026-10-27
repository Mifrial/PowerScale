import { describe, expect, it } from 'vitest';
import { rangedHitDifficultyRows } from '@/modules/Roleplay/Game/Utils/rangedHitDifficultyRows';

describe('rangedHitDifficultyRows', () => {
  it('раскладывает защиту, минимум, укрытие и полосы дальности', () => {
    expect(
      rangedHitDifficultyRows({
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
      }),
    ).toEqual([
      { label: 'Реакция', value: 'уклон' },
      { label: 'Защита', value: '1↓' },
      { label: 'Укрытие', value: '1↓' },
      { label: 'Минимум', value: '1↓' },
      { label: 'База', value: '1↓ + 1↓ + 1↓ = 3↓' },
      { label: 'Дальность', value: '11 ипари · дальнобойность 10 → +1 размер' },
    ]);
  });

  it('без полос пишет «без штрафа», а игнор даёт нулевую защиту', () => {
    expect(
      rangedHitDifficultyRows({
        cover: 0,
        defense_result: 0,
        defense_value: { base: 0, size: 0 },
        reaction: 'ignore',
        range_size: 1,
        falloff_value: { base: 5, size: 0 },
        distance_ipari: 3,
        distance_value: { base: 3, size: 0 },
      }).map((row) => row.value),
    ).toEqual(['игнор', '0', '0', '3 ипари · дальнобойность 5 → без штрафа']);
  });
});
