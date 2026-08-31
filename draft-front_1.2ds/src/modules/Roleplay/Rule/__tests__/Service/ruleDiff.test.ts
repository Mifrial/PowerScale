import { describe, it, expect } from 'vitest';
import { ruleDiffService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDiffService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';

function rule(code: string, name: string, overrides: Partial<Rule> = {}): Rule {
  return {
    id: null,
    code,
    type: 'simple',
    name,
    description: `Описание ${name}`,
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

function spec(value: Record<string, unknown>): RuleSpec {
  return value as unknown as RuleSpec;
}

describe('classifyDraftDiff', () => {
  it('same code при разных storage id — changed, не added', () => {
    const published = [rule('r1', 'Старое')];
    const draft = [rule('r1', 'Старое', { id: null, description: 'Изменённое описание' })];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed.map((r) => r.code)).toEqual(['r1']);
    expect(diff.added).toEqual([]);
  });

  it('новые правила отделяются от изменённых', () => {
    const published = [rule('r1', 'Старое'), rule('r2', 'Второе')];
    const draft = [rule('r1', 'Старое', { description: 'Изменённое описание' }), rule('r3', 'Третье')];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed.map((r) => r.code)).toEqual(['r1']);
    expect(diff.added.map((r) => r.code)).toEqual(['r3']);
  });

  it('samePayload считает равными правила с разными id и spaceId', () => {
    const left = rule('r1', 'Одно', { id: null, spaceId: 1 });
    const right = rule('r1', 'Одно', { id: null, spaceId: 2 });
    expect(ruleDiffService.samePayload(left, right)).toBe(true);
  });

  it('правило в draft без изменений против published не попадает в блоки', () => {
    const published = [rule('r1', 'Одно')];
    const draft = [rule('r1', 'Одно')];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed).toEqual([]);
    expect(diff.added).toEqual([]);
  });

  it('служебные временные поля не влияют на сравнение', () => {
    const published = [rule('r1', 'Одно', { updatedAt: '2026-01-01T00:00:00Z' })];
    const draft = [rule('r1', 'Одно', { updatedAt: '2026-02-01T00:00:00Z' })];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed).toEqual([]);
  });

  it('порядок ключей в spec (и во вложенных объектах) не даёт ложного changed', () => {
    const published = [
      rule('r1', 'Одно', { spec: spec({ type: 'ability', requirements: [], zones: { melee: { base: 1, size: 0 } } }) }),
    ];
    const draft = [
      rule('r1', 'Одно', { spec: spec({ zones: { melee: { size: 0, base: 1 } }, requirements: [], type: 'ability' }) }),
    ];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed).toEqual([]);
  });

  it('изменение содержимого spec при том же порядке ключей помечается как changed', () => {
    const published = [rule('r1', 'Одно', { spec: spec({ type: 'ability', requirements: [] }) })];
    const draft = [
      rule('r1', 'Одно', {
        spec: spec({ type: 'ability', requirements: [{ type: 'has_keyword', keyword_code: 'a' }] }),
      }),
    ];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed.map((r) => r.code)).toEqual(['r1']);
  });

  it('изменение порядка элементов массива в spec помечается как changed', () => {
    const reorder = spec({
      type: 'ability',
      requirements: [
        { type: 'has_keyword', keyword_code: 'x' },
        { type: 'has_keyword', keyword_code: 'y' },
      ],
    });
    const reorderSwapped = spec({
      type: 'ability',
      requirements: [
        { type: 'has_keyword', keyword_code: 'y' },
        { type: 'has_keyword', keyword_code: 'x' },
      ],
    });
    const published = [rule('r1', 'Одно', { spec: reorder })];
    const draft = [rule('r1', 'Одно', { spec: reorderSwapped })];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed.map((r) => r.code)).toEqual(['r1']);
  });

  it('пустой черновик', () => {
    const diff = ruleDiffService.classifyDraftDiff([rule('r1', 'Одно')], []);
    expect(diff.changed).toEqual([]);
    expect(diff.added).toEqual([]);
  });
});

describe('groupProblems', () => {
  it('группирует ошибки по правилу, сохраняя порядок', () => {
    const items = [
      { ruleCode: 'a', ruleName: 'A', message: 'ошибка 1' },
      { ruleCode: 'b', ruleName: 'B', message: 'ошибка 2' },
      { ruleCode: 'a', ruleName: 'A', message: 'ошибка 3' },
    ];
    const problems = ruleDiffService.groupProblems(items);
    expect(problems.map((p) => p.ruleCode)).toEqual(['a', 'b']);
    expect(problems[0].messages).toEqual(['ошибка 1', 'ошибка 3']);
    expect(problems[1].messages).toEqual(['ошибка 2']);
  });

  it('пустой массив', () => {
    expect(ruleDiffService.groupProblems([])).toEqual([]);
  });
});
