import type { LoreArticleSummary } from '@/modules/Roleplay/Lore/Dto/LoreArticleSummary';
import type { LoreSection } from '@/modules/Roleplay/Lore/Dto/LoreSection';
import type { LoreTreeNode } from '@/modules/Roleplay/Lore/Dto/LoreTreeNode';

const ROOT_CODE = '__root__';

export const LORE_ROOT_SECTION_CODE = ROOT_CODE;

/** Дерево разделов с вложенными статьями; статьи без раздела — в псевдораздел. */
export function buildLoreTree(
  sections: LoreSection[],
  articles: LoreArticleSummary[],
  rootTitle = 'Без раздела',
): LoreTreeNode[] {
  const nodes = new Map<string, LoreTreeNode>();
  const allSections: LoreSection[] = [...sections];
  const hasRootArticles = articles.some((article) => article.sectionCode === null);
  if (hasRootArticles) {
    allSections.push({
      id: 0,
      code: ROOT_CODE,
      name: rootTitle,
      description: '',
      parentCode: null,
      sortOrder: Number.MAX_SAFE_INTEGER,
      icon: 'mdi-folder-outline',
    });
  }

  for (const section of allSections) {
    nodes.set(section.code, { section, children: [], articles: [] });
  }

  for (const article of articles) {
    const node = nodes.get(article.sectionCode ?? ROOT_CODE);
    if (node) node.articles.push(article);
  }

  const roots: LoreTreeNode[] = [];
  for (const node of nodes.values()) {
    const parentCode = node.section.parentCode;
    const parent = parentCode === null ? null : nodes.get(parentCode);
    if (parent) {
      parent.children.push(node);
    } else {
      roots.push(node);
    }
  }

  const bySection = (left: LoreTreeNode, right: LoreTreeNode): number =>
    left.section.sortOrder - right.section.sortOrder || left.section.name.localeCompare(right.section.name);
  const sortNode = (node: LoreTreeNode): void => {
    node.children.sort(bySection);
    node.articles.sort((left, right) => left.sortOrder - right.sortOrder || left.title.localeCompare(right.title));
    node.children.forEach(sortNode);
  };
  roots.sort(bySection);
  roots.forEach(sortNode);

  return roots;
}

/** Плоский список кодов разделов в порядке обхода. */
export function flattenSectionCodes(nodes: LoreTreeNode[]): string[] {
  const codes: string[] = [];
  const visit = (node: LoreTreeNode): void => {
    if (node.section.code !== ROOT_CODE) codes.push(node.section.code);
    node.children.forEach(visit);
  };
  nodes.forEach(visit);

  return codes;
}
