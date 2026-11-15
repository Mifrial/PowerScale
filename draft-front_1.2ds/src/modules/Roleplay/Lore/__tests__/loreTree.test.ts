import { describe, expect, it } from 'vitest';
import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreSection } from '@/modules/Roleplay/Lore/Dto/LoreSection';
import { buildLoreTree } from '@/modules/Roleplay/Lore/Utils/loreTree';
import { flattenLoreNav } from '@/modules/Roleplay/Lore/Utils/loreNav';

function section(code: string, name: string, parentCode: string | null, sortOrder: number): LoreSection {
  return { id: sortOrder, code, name, description: '', parentCode, sortOrder, icon: '' };
}

function article(code: string, title: string, sectionCode: string | null, sortOrder = 0): LoreArticleSummary {
  return {
    id: sortOrder + 1,
    code,
    title,
    summary: '',
    sectionCode,
    sortOrder,
    keywordIds: [],
    status: 'published',
    visibility: 'public',
    isArchived: false,
    version: 1,
    updatedAt: 0,
  };
}

describe('buildLoreTree', () => {
  it('раскладывает статьи по разделам и сохраняет вложенность', () => {
    const tree = buildLoreTree(
      [section('history', 'История', null, 0), section('wars', 'Войны', 'history', 1)],
      [article('fall', 'Падение', 'history'), article('battle', 'Битва', 'wars')],
    );

    expect(tree).toHaveLength(1);
    expect(tree[0]?.section.code).toBe('history');
    expect(tree[0]?.articles.map((item) => item.code)).toEqual(['fall']);
    expect(tree[0]?.children[0]?.articles.map((item) => item.code)).toEqual(['battle']);
  });

  it('статьи без раздела попадают в псевдораздел', () => {
    const tree = buildLoreTree([], [article('orphan', 'Сирота', null)]);

    expect(tree).toHaveLength(1);
    expect(tree[0]?.section.name).toBe('Без раздела');
    expect(tree[0]?.articles.map((item) => item.code)).toEqual(['orphan']);
  });

  it('плоская навигация идёт по разделам и статьям', () => {
    const tree = buildLoreTree(
      [section('history', 'История', null, 0), section('wars', 'Войны', 'history', 1)],
      [article('fall', 'Падение', 'history'), article('battle', 'Битва', 'wars')],
    );
    const rows = flattenLoreNav(tree);

    expect(rows.map((row) => (row.kind === 'section' ? `s:${row.code}` : `a:${row.article.code}`))).toEqual([
      's:history',
      'a:fall',
      's:wars',
      'a:battle',
    ]);
  });
});
