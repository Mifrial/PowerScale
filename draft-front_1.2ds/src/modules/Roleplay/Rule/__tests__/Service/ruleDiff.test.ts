import { describe, it, expect } from 'vitest';
import { ruleDiffService } from '@/modules/Roleplay/Rule/Service/RuleDiffService';
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

describe('classifyDraftDiff', () => {
  it('новые правила отделяются от изменённых', () => {
    const published = [rule('r1', 'Старое'), rule('r2', 'Второе')];
    const draft = [rule('r1', 'Старое', { description: 'Изменённое описание' }), rule('r3', 'Третье')];
    const diff = ruleDiffService.classifyDraftDiff(published, draft);
    expect(diff.changed.map((r) => r.id)).toEqual(['r1']);
    expect(diff.added.map((r) => r.id)).toEqual(['r3']);
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
