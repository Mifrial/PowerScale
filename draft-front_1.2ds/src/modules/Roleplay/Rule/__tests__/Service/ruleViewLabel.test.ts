import { describe, it, expect } from 'vitest';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';

const rules: Rule[] = [
  {
    id: null,
    code: 'strength',
    type: 'characteristic',
    name: 'Сила',
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: null,
    code: 'fire',
    type: 'damage_type',
    name: 'Огонь',
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: null,
    code: 'sense-hearing',
    type: 'sense',
    name: 'Слух',
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  },
  {
    id: null,
    code: 'innate',
    type: 'source',
    name: 'Врождённый',
    description: '',
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
  },
];

const keywords: Keyword[] = [{ id: 1, code: 'weapon', name: 'Оружие', active: true }];

describe('RuleViewLabelService', () => {
  it('подписывает сопротивление и чувство', () => {
    expect(
      ruleViewLabelService.grant(
        {
          type: 'resistance',
          damage_type_code: 'fire',
          value: { type: 'parameter', parameter_code: 'x', per_unit: 2 },
          source_code: 'innate',
        },
        rules,
        keywords,
      ),
    ).toContain('x × 2');
    expect(
      ruleViewLabelService.grant(
        {
          type: 'sense_modify',
          sense_code: 'sense-hearing',
          amount: { type: 'fixed', value: 3 },
          source_code: 'innate',
        },
        rules,
        keywords,
      ),
    ).toContain('Слух');
  });

  it('подписывает ops модификатора значениями', () => {
    expect(ruleViewLabelService.modifierOp({ type: 'weight', factor: 0.5 }, rules, keywords)).toBe('Вес: ×0.5');
    expect(ruleViewLabelService.modifierOp({ type: 'keyword', add: ['weapon'] }, rules, keywords)).toBe(
      'Признаки: + Оружие',
    );
  });
});
