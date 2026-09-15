import { describe, it, expect } from 'vitest';
import { mockEthnicities } from '@/modules/Roleplay/Rule/Mock/mockEthnicities';
import { ethnicityTreeService } from '@/modules/Roleplay/Rule/Service/Instance/ethnicityTreeService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

describe('EthnicityTreeService', () => {
  const rules = mockEthnicities;

  it('сток Раден и Содружество не выбираются, государства и Форн — да', () => {
    const options = ethnicityTreeService.pickableOptions(rules);
    expect(options.some((option) => option.code === 'sri')).toBe(true);
    expect(options.some((option) => option.code === 'bri')).toBe(true);
    expect(options.some((option) => option.code === 'rs')).toBe(true);
    expect(options.some((option) => option.code === 'forn')).toBe(true);
    expect(options.some((option) => option.code === 'ulay')).toBe(true);
    expect(options.some((option) => option.code === 'raden')).toBe(false);
    expect(options.some((option) => option.code === 'evs')).toBe(false);
  });

  it('цикл parent_code ловится', () => {
    const cyclic: Rule[] = [
      {
        ...rules[0],
        code: 'a',
        spec: { type: 'ethnicity', role: 'stock', parent_code: 'b', race_codes: [], language_codes: [], usages: [] },
      },
      {
        ...rules[0],
        id: 2,
        code: 'b',
        spec: { type: 'ethnicity', role: 'stock', parent_code: 'a', race_codes: [], language_codes: [], usages: [] },
      },
    ];
    expect(ethnicityTreeService.findCycle(cyclic)).toContain('a');
  });
});
