import { describe, expect, it } from 'vitest';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/Service/Instance/aggregateSourceDeltasService';

describe('aggregateSourceDeltas', () => {
  it('от одного источника берёт макс+ и мин−', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta([
        { source_code: 'tool', delta: -1 },
        { source_code: 'tool', delta: -2 },
        { source_code: 'tool', delta: 1 },
      ]),
    ).toBe(-1);
  });

  it('разные источники суммируются', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta([
        { source_code: 'tool', delta: -1 },
        { source_code: 'manual', delta: 2 },
        { source_code: 'cover', delta: -1 },
      ]),
    ).toBe(0);
  });

  it('два плюса без source не схлопываются', () => {
    expect(
      aggregateSourceDeltasService.netSourceDelta([
        { source_code: null, delta: 1 },
        { source_code: null, delta: 1 },
      ]),
    ).toBe(2);
  });

  it('нулевой delta не выбирается', () => {
    expect(
      aggregateSourceDeltasService.aggregateSourceDeltas([
        { source_code: 'tool', delta: 0 },
        { source_code: 'tool', delta: 2 },
        { source_code: null, delta: 0 },
      ]),
    ).toEqual([{ source_code: 'tool', delta: 2 }]);
  });

  it('два бонуса разных источников складываются; дубль слабее отбрасывается', () => {
    expect(
      aggregateSourceDeltasService.aggregateSourceDeltas([
        { source_code: 'training', delta: 3 },
        { source_code: 'training', delta: 1 },
        { source_code: 'perfection', delta: 1 },
      ]),
    ).toEqual([
      { source_code: 'training', delta: 3 },
      { source_code: 'perfection', delta: 1 },
    ]);
    expect(
      aggregateSourceDeltasService.netSourceDelta([
        { source_code: 'training', delta: 3 },
        { source_code: 'training', delta: 1 },
        { source_code: 'perfection', delta: 1 },
      ]),
    ).toBe(4);
  });
});
