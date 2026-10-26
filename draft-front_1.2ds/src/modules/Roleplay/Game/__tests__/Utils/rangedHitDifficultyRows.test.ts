import { describe, expect, it } from 'vitest';
import { rangedHitDifficultyDetailRows } from '@/modules/Roleplay/Game/Utils/rangedHitDifficultyRows';

describe('rangedHitDifficultyDetailRows', () => {
  it('раскладывает успехи + ½ + укрытие и полосы', () => {
    expect(
      rangedHitDifficultyDetailRows({
        cover: 2,
        defense_result: 0,
        reaction: 'ignore',
        range_size: 0,
        distance_ipari: 1,
      }),
    ).toEqual([
      { label: 'Реакция', value: 'игнор' },
      { label: 'Результат защиты', value: '0' },
      { label: 'Укрытие', value: '2' },
      { label: 'База', value: '0 + ½ + 2 = 2.5' },
      { label: 'Дальность', value: 'без полос (1 ипари)' },
    ]);
    expect(
      rangedHitDifficultyDetailRows({
        cover: 2,
        defense_result: 2,
        reaction: 'dodge',
        range_size: 2,
        distance_ipari: 6,
      }).map((row) => row.value),
    ).toEqual(['уклон', '2', '2', '2 + ½ + 2 = 4.5', '+1 размер (6 ипари)']);
  });
});
