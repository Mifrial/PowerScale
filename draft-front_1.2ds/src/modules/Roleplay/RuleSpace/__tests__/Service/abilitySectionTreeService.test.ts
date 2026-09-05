import { describe, expect, it } from 'vitest';
import type { AbilitySection } from '@/modules/Roleplay/Space/Dto/AbilitySection';
import { abilitySectionTreeService } from '@/modules/Roleplay/Space/Service/Instance/abilitySectionTreeService';

const sections: AbilitySection[] = [
  { code: 'body', name: 'Тело', parentCode: null, sortOrder: 20 },
  { code: 'melee', name: 'Ближний бой', parentCode: 'body', sortOrder: 10 },
  { code: 'maneuvers', name: 'Манёвры', parentCode: 'melee', sortOrder: 20 },
  { code: 'weapon', name: 'Оружие', parentCode: 'melee', sortOrder: 10 },
];

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
});
