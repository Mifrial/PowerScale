import { describe, expect, it } from 'vitest';
import { reactive } from 'vue';
import { revisionFileService } from '@/modules/Roleplay/Space/Service/Instance/revisionFileService';
import { generateRevisionRules } from '@/modules/Roleplay/Space/Mock/mockSpaces';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision';

function sampleRevision(rules: Rule[]): SpaceRevision<Rule> {
  return {
    revision: 3,
    publishedAt: '2026-01-01T00:00:00Z',
    spaceCode: 'src',
    spaceName: 'Источник',
    rules,
  };
}

function rule(code: string, name: string, overrides: Partial<Rule> = {}): Rule {
  return {
    id: `id-${code}`,
    code,
    type: 'simple',
    name,
    description: `Описание ${name}`,
    spaceId: 1,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

function collectStrings(value: unknown, acc: string[]): void {
  if (typeof value === 'string') acc.push(value);
  else if (Array.isArray(value)) value.forEach((item) => collectStrings(item, acc));
  else if (typeof value === 'object' && value !== null) {
    Object.values(value).forEach((item) => collectStrings(item, acc));
  }
}

describe('RevisionFileService', () => {
  it('parse отклоняет мусор и чужой format', () => {
    expect(() => revisionFileService.parse('{')).toThrow('JSON');
    expect(() => revisionFileService.parse(JSON.stringify({ format: 'other' }))).toThrow('не файл ревизии');
  });

  it('serialize/parse круг и diff пропускает равный payload с другими id', () => {
    const published = [rule('a', 'А', { id: 'local-a', spaceId: 2 })];
    const fileRules = [rule('a', 'А', { id: 'foreign-a', spaceId: 9 }), rule('b', 'Б')];
    const file = revisionFileService.serialize(sampleRevision(fileRules));
    const parsed = revisionFileService.parse(JSON.stringify(file));
    expect(parsed.revision.rules).toHaveLength(2);

    const diff = revisionFileService.diffAgainstPublished(parsed.revision.rules, published, 2, {
      removeMissing: false,
      existingRemovedCodes: [],
    });
    expect(diff.unchangedCount).toBe(1);
    expect(diff.added.map((item) => item.code)).toEqual(['b']);
    expect(diff.changed).toEqual([]);
    expect(diff.removedCodes).toEqual([]);
  });

  it('removeMissing ставит к удалению только code, которых нет в файле', () => {
    const published = [rule('keep', 'Оставить'), rule('gone', 'Убрать')];
    const fileRules = [rule('keep', 'Оставить')];
    const diff = revisionFileService.diffAgainstPublished(fileRules, published, 1, {
      removeMissing: true,
      existingRemovedCodes: [],
    });
    expect(diff.removedCodes).toEqual(['gone']);
    expect(diff.unchangedCount).toBe(1);
  });

  it('diff клонирует Vue-прокси правил без structuredClone-ошибки', () => {
    const published = [rule('keep', 'Оставить')];
    const fileRules = reactive([rule('keep', 'Оставить', { description: 'иначе' }), rule('new', 'Новое')]);
    expect(() =>
      revisionFileService.diffAgainstPublished(fileRules, published, 1, {
        removeMissing: false,
        existingRemovedCodes: [],
      }),
    ).not.toThrow();
  });
});

describe('срез ревизии: spec ссылается по code, не по id', () => {
  it('ни одно spec не содержит id другого правила среза', () => {
    const rules = generateRevisionRules(2, 12);
    const ids = new Set(rules.map((item) => item.id));
    for (const item of rules) {
      const strings: string[] = [];
      collectStrings(item.spec, strings);
      collectStrings(item.mechanic_payload, strings);
      for (const text of strings) {
        if (ids.has(text) && text !== item.id) {
          throw new Error(`${item.code} ссылается на id ${text}`);
        }
      }
    }
  });
});
