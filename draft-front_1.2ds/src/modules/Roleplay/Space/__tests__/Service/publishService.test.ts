import { describe, it, expect } from 'vitest';
import { publishService } from '@/modules/Roleplay/Space/Service/Instance/publishService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function rule(id: string, name: string, overrides: Partial<Rule> = {}): Rule {
  return {
    id,
    code: id,
    type: 'simple',
    name,
    description: `Описание ${name}`,
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('PublishService.prepare', () => {
  it('классифицирует добавленные и изменённые правила черновика', () => {
    const published = [rule('r1', 'Одно')];
    const draft = [rule('r1', 'Одно', { description: 'Изменено' }), rule('r2', 'Новое')];

    const summary = publishService.prepare(published, draft, draft, []);

    expect(summary.added.map((r) => r.id)).toEqual(['r2']);
    expect(summary.changed.map((r) => r.id)).toEqual(['r1']);
  });

  it('пустой черновик не даёт изменений', () => {
    const published = [rule('r1', 'Одно')];

    const summary = publishService.prepare(published, [], published, []);

    expect(summary.added).toEqual([]);
    expect(summary.changed).toEqual([]);
  });

  it('собирает проблему по отсутствующей ссылке в problems', () => {
    const effective = [
      rule('r1', 'Меч', {
        type: 'item',
        spec: {
          category: 'equipment',
          cost_gm: 500,
          weight: { base: 1, size: 0 },
          special_rule_codes: ['no-such-rule'],
        },
      }),
    ];

    const summary = publishService.prepare([], effective, effective, []);

    expect(summary.problems.length).toBe(1);
    expect(summary.problems[0].ruleCode).toBe('r1');
    expect(summary.problems[0].messages.some((m) => m.includes('no-such-rule'))).toBe(true);
    expect(summary.spaceErrors).toEqual([]);
  });

  it('валидный набор без ошибок', () => {
    const effective = [rule('r1', 'Одно')];

    const summary = publishService.prepare([], effective, effective, []);

    expect(summary.problems).toEqual([]);
    expect(summary.spaceErrors).toEqual([]);
  });

  it('обнаруживает цикл в цепочке видов в spaceErrors', () => {
    const effective = [
      rule('s1', 'Вид A', { type: 'species', spec: { parent_race_code: 's2', abilities: [] } }),
      rule('s2', 'Вид B', { type: 'species', spec: { parent_race_code: 's1', abilities: [] } }),
    ];

    const summary = publishService.prepare([], effective, effective, []);

    expect(summary.spaceErrors.length).toBe(1);
    expect(summary.spaceErrors[0]).toContain('Цикл');
  });
});
