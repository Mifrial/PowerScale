import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreNavRow } from '@/modules/Roleplay/Lore/Dto/LoreNavRow';
import type { LoreTreeNode } from '@/modules/Roleplay/Lore/Dto/LoreTreeNode';
import { LORE_ROOT_SECTION_CODE } from '@/modules/Roleplay/Lore/Utils/loreTree';

/** Плоский список разделов и статей для боковой навигации. */
export function flattenLoreNav(nodes: LoreTreeNode[], depth = 0): LoreNavRow[] {
  const rows: LoreNavRow[] = [];
  for (const node of nodes) {
    if (node.section.code !== LORE_ROOT_SECTION_CODE) {
      rows.push({
        kind: 'section',
        depth,
        code: node.section.code,
        name: node.section.name,
        icon: node.section.icon,
      });
    }

    const articleDepth = node.section.code === LORE_ROOT_SECTION_CODE ? depth : depth + 1;
    for (const article of node.articles) {
      rows.push({ kind: 'article', depth: articleDepth, article });
    }

    rows.push(...flattenLoreNav(node.children, depth + 1));
  }

  return rows;
}

/** Все статьи дерева в порядке обхода. */
export function treeArticles(nodes: LoreTreeNode[]): LoreArticleSummary[] {
  return nodes.flatMap((node) => [...node.articles, ...treeArticles(node.children)]);
}
