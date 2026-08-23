import { describe, expect, it } from 'vitest';
import { aggregateSourceDeltas, netSourceDelta } from '@/modules/Roleplay/Rule/Utils/aggregateSourceDeltas';

describe('aggregateSourceDeltas', () => {
  it('от одного источника берёт макс+ и мин−', () => {
    expect(
      netSourceDelta([
        { source_code: 'tool', delta: -1 },
        { source_code: 'tool', delta: -2 },
        { source_code: 'tool', delta: 1 },
      ]),
    ).toBe(-1);
  });

  it('разные источники суммируются', () => {
    expect(
      netSourceDelta([
        { source_code: 'tool', delta: -1 },
        { source_code: 'manual', delta: 2 },
        { source_code: 'cover', delta: -1 },
      ]),
    ).toBe(0);
  });

  it('два бонуса разных источников складываются; дубль слабее отбрасывается', () => {
    expect(
      aggregateSourceDeltas([
        { source_code: 'training', delta: 3 },
        { source_code: 'training', delta: 1 },
        { source_code: 'perfection', delta: 1 },
      ]),
    ).toEqual([
      { source_code: 'training', delta: 3 },
      { source_code: 'perfection', delta: 1 },
    ]);
    expect(
      netSourceDelta([
        { source_code: 'training', delta: 3 },
        { source_code: 'training', delta: 1 },
        { source_code: 'perfection', delta: 1 },
      ]),
    ).toBe(4);
  });
});
