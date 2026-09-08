import { describe, expect, it } from 'vitest';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { abilitySectionTreeService } from '@/modules/Roleplay/RuleSpace/Service/Instance/abilitySectionTreeService';

const sections: AbilitySection[] = [
  { code: 'body', name: 'Тело', parentCode: null, sortOrder: 20 },
  { code: 'melee', name: 'Ближний бой', parentCode: 'body', sortOrder: 10 },
  { code: 'maneuvers', name: 'Манёвры', parentCode: 'melee', sortOrder: 20 },
  { code: 'weapon', name: 'Оружие', parentCode: 'melee', sortOrder: 10 },
];

function rule(code: string, catalogSection: string): Rule {
  return {
    id: 1,
    code,
    type: 'simple',
    name: code,
    description: '',
    spaceId: 1,
    createdAt: 1,
    catalogSection,
  };
}

describe('AbilitySectionTreeService', () => {
  it('does not invent sections when a revision has no section data', () => {
    expect(abilitySectionTreeService.normalize()).toEqual([]);
  });

  it('flattens sections into a sorted tree with paths', () => {
    expect(abilitySectionTreeService.flatten(sections).map((section) => section.path)).toEqual([
      'Тело',
      'Тело → Ближний бой',
      'Тело → Ближний бой → Оружие',
      'Тело → Ближний бой → Манёвры',
    ]);
  });

  it('returns descendants for parent section filters', () => {
    expect(abilitySectionTreeService.descendantCodes('melee', sections)).toEqual(['melee', 'weapon', 'maneuvers']);
  });

  it('returns an area subtree without exposing its navigation root', () => {
    expect(
      abilitySectionTreeService.subtreeForArea('development', [
        { code: 'development', name: 'Приобретённые', parentCode: null, sortOrder: 1, catalogRootFor: 'development' },
        { code: 'mental', name: 'Ментальные', parentCode: 'development', sortOrder: 1 },
        { code: 'medicine', name: 'Медицина', parentCode: 'development', sortOrder: 2 },
      ]),
    ).toEqual([
      { code: 'mental', name: 'Ментальные', parentCode: null, sortOrder: 1 },
      { code: 'medicine', name: 'Медицина', parentCode: null, sortOrder: 2 },
    ]);
  });

  it('detects missing parents and cycles', () => {
    expect(
      abilitySectionTreeService.validate([
        { code: 'a', name: 'A', parentCode: 'missing', sortOrder: 1 },
        { code: 'b', name: 'B', parentCode: 'c', sortOrder: 2 },
        { code: 'c', name: 'C', parentCode: 'b', sortOrder: 3 },
      ]),
    ).toEqual(['Родительская секция не найдена: missing', 'Цикл в дереве секций: b', 'Цикл в дереве секций: c']);
  });

  it('rejects duplicate catalogRootFor', () => {
    expect(
      abilitySectionTreeService.validate([
        { code: 'a', name: 'A', parentCode: null, sortOrder: 1, catalogRootFor: 'base' },
        { code: 'b', name: 'B', parentCode: null, sortOrder: 2, catalogRootFor: 'base' },
      ]),
    ).toEqual(['Корень каталога «base» уже задан у a']);
  });

  it('keeps unknown catalogRootFor', () => {
    expect(
      abilitySectionTreeService.validate([
        { code: 'a', name: 'A', parentCode: null, sortOrder: 1, catalogRootFor: 'custom-area' },
      ]),
    ).toEqual([]);
  });

  it('keeps legacy flat section lists as roots', () => {
    expect(
      abilitySectionTreeService.normalize([
        { code: 'legacy-a', name: 'A', parentCode: null, sortOrder: 2 },
        { code: 'legacy-b', name: 'B', parentCode: null, sortOrder: 1 },
      ]),
    ).toEqual([
      { code: 'legacy-b', name: 'B', parentCode: null, sortOrder: 1 },
      { code: 'legacy-a', name: 'A', parentCode: null, sortOrder: 2 },
    ]);
  });

  it('adds a root section', () => {
    const result = abilitySectionTreeService.addSection(sections, {
      code: 'extra',
      name: 'Ещё',
      parentCode: null,
      sortOrder: 0,
    });
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'extra')?.parentCode).toBeNull();
  });

  it('adds a child section', () => {
    const result = abilitySectionTreeService.addSection(sections, {
      code: 'shield',
      name: 'Щит',
      parentCode: 'melee',
      sortOrder: 0,
    });
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'shield')?.parentCode).toBe('melee');
  });

  it('rejects duplicate code on add', () => {
    const result = abilitySectionTreeService.addSection(sections, {
      code: 'body',
      name: 'Дубль',
      parentCode: null,
      sortOrder: 1,
    });
    expect(result.errors).toEqual(['Код секции дублируется: body']);
    expect(result.sections).toEqual(abilitySectionTreeService.normalize(sections));
  });

  it('updates the name without changing the code', () => {
    const result = abilitySectionTreeService.updateSection(sections, 'weapon', { name: 'Оружие ближнего боя' });
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'weapon')?.name).toBe('Оружие ближнего боя');
  });

  it('updates parentCode', () => {
    const result = abilitySectionTreeService.updateSection(sections, 'weapon', { parentCode: 'body' });
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'weapon')?.parentCode).toBe('body');
  });

  it('updates sortOrder among siblings', () => {
    const result = abilitySectionTreeService.updateSection(sections, 'maneuvers', { sortOrder: 5 });
    expect(result.errors).toEqual([]);
    const melee = result.sections.filter((section) => section.parentCode === 'melee');
    expect(melee.map((section) => section.code)).toEqual(['maneuvers', 'weapon']);
  });

  it('drops a node as a child of the target', () => {
    const result = abilitySectionTreeService.dropSection(sections, 'maneuvers', 'body', 'child');
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'maneuvers')?.parentCode).toBe('body');
  });

  it('places a node among new siblings and reindexes them', () => {
    const result = abilitySectionTreeService.placeSection(sections, 'maneuvers', 'body', 0);
    expect(result.errors).toEqual([]);
    expect(result.sections.find((section) => section.code === 'maneuvers')?.parentCode).toBe('body');
    const bodyChildren = result.sections.filter((section) => section.parentCode === 'body');
    expect(bodyChildren.map((section) => section.code)).toEqual(['maneuvers', 'melee']);
    expect(bodyChildren.map((section) => section.sortOrder)).toEqual([10, 20]);
  });

  it('rejects placing a node under its descendant', () => {
    const result = abilitySectionTreeService.placeSection(sections, 'body', 'weapon', 0);
    expect(result.errors).toEqual(['Цикл в дереве секций: body']);
    expect(result.sections).toEqual(abilitySectionTreeService.normalize(sections));
  });

  it('removes a leaf', () => {
    const result = abilitySectionTreeService.removeSection(sections, 'weapon');
    expect(result.errors).toEqual([]);
    expect(result.sections.some((section) => section.code === 'weapon')).toBe(false);
  });

  it('refuses to remove a node with children', () => {
    const result = abilitySectionTreeService.removeSection(sections, 'melee');
    expect(result.errors).toEqual(['Нельзя удалить секцию melee: есть дочерние секции']);
  });

  it('refuses to remove a section referenced by a rule', () => {
    const result = abilitySectionTreeService.removeSection(sections, 'weapon', [rule('strike', 'weapon')]);
    expect(result.errors).toEqual(['Секцию weapon используют правила: strike']);
  });

  it('reindexes siblings after normalizeSortOrder', () => {
    const result = abilitySectionTreeService.normalizeSortOrder(
      [
        { code: 'a', name: 'A', parentCode: null, sortOrder: 40 },
        { code: 'b', name: 'B', parentCode: null, sortOrder: 7 },
      ],
      null,
    );
    expect(result.map((section) => [section.code, section.sortOrder])).toEqual([
      ['b', 10],
      ['a', 20],
    ]);
  });
});
