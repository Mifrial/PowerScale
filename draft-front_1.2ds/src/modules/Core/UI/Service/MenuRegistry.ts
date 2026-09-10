import type { MenuContribution } from '@/modules/Core/UI/Interface/Navigation/MenuContribution';
import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import type { MenuSection } from '@/modules/Core/UI/Dto/Navigation/MenuSection';
import type { MenuTreeItem } from '@/modules/Core/UI/Dto/Navigation/MenuTreeItem';
import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';
import type { MenuTreeSection } from '@/modules/Core/UI/Dto/Navigation/MenuTreeSection';

type MenuListener = () => void;

/**
 * Реестр левого меню: секции и пункты без ролевой модели.
 * Вклады актора помечают только узлы, зарегистрированные внутри apply.
 */
export class MenuRegistry {
  private readonly sections = new Map<string, MenuSection>();
  private readonly items = new Map<string, MenuItem>();
  private readonly contributions = new Map<string, MenuContribution>();
  private readonly ownedNodeIds = new Map<string, Set<string>>();
  private readonly listeners = new Set<MenuListener>();
  private currentContributionId: string | null = null;
  private revisionCounter = 0;

  get revision(): number {
    return this.revisionCounter;
  }

  subscribe(listener: MenuListener): () => void {
    this.listeners.add(listener);

    return () => {
      this.listeners.delete(listener);
    };
  }

  registerMenuSection(menuSection: MenuSection): void {
    this.assertUniqueId(menuSection.id);
    this.sections.set(menuSection.id, menuSection);
    this.trackOwned(menuSection.id);
    this.bump();
  }

  registerMenuItem(menuItem: MenuItem): void {
    this.assertUniqueId(menuItem.id);
    this.items.set(menuItem.id, menuItem);
    this.trackOwned(menuItem.id);
    this.bump();
  }

  registerMenuContribution(contribution: MenuContribution): void {
    if (this.contributions.has(contribution.id)) {
      throw new Error(`Вклад меню «${contribution.id}» уже зарегистрирован`);
    }
    this.contributions.set(contribution.id, contribution);
    this.ownedNodeIds.set(contribution.id, new Set());
  }

  applyMenuContributions(actor: unknown): void {
    for (const contribution of this.contributions.values()) {
      this.removeOwned(contribution.id);
      this.currentContributionId = contribution.id;
      try {
        contribution.apply(actor);
      } finally {
        this.currentContributionId = null;
      }
    }
    this.assertMenuTree();
    this.bump();
  }

  getMenuTree(): MenuTreeNode[] {
    this.assertMenuTree();

    const sectionNodes = new Map<string, MenuTreeSection>();
    for (const menuSection of this.sections.values()) {
      sectionNodes.set(menuSection.id, {
        kind: 'menuSection',
        id: menuSection.id,
        title: menuSection.title,
        icon: menuSection.icon,
        order: menuSection.order,
        children: [],
      });
    }

    const roots: MenuTreeNode[] = [];

    for (const menuItem of this.items.values()) {
      const treeItem = this.toTreeItem(menuItem);
      if (!menuItem.sectionId) {
        roots.push(treeItem);
        continue;
      }
      const parent = sectionNodes.get(menuItem.sectionId);
      if (!parent) {
        throw new Error(`Пункт меню «${menuItem.id}» ссылается на неизвестную секцию «${menuItem.sectionId}»`);
      }
      parent.children.push(treeItem);
    }

    for (const menuSection of this.sections.values()) {
      const node = sectionNodes.get(menuSection.id);
      if (!node) continue;
      if (!menuSection.parentId) {
        roots.push(node);
        continue;
      }
      const parent = sectionNodes.get(menuSection.parentId);
      if (!parent) {
        throw new Error(`Секция меню «${menuSection.id}» ссылается на неизвестного родителя «${menuSection.parentId}»`);
      }
      parent.children.push(node);
    }

    const pruned = this.pruneEmptySections(roots);
    this.sortNodes(pruned);
    for (const node of pruned) {
      this.sortTree(node);
    }

    return pruned;
  }

  assertMenuTree(): void {
    for (const menuSection of this.sections.values()) {
      if (!menuSection.parentId) continue;
      const parent = this.sections.get(menuSection.parentId);
      if (!parent) {
        throw new Error(`Секция меню «${menuSection.id}» ссылается на неизвестного родителя «${menuSection.parentId}»`);
      }
    }
    for (const menuItem of this.items.values()) {
      if (!menuItem.sectionId) continue;
      if (!this.sections.has(menuItem.sectionId)) {
        throw new Error(`Пункт меню «${menuItem.id}» ссылается на неизвестную секцию «${menuItem.sectionId}»`);
      }
    }
    this.assertNoCycles();
  }

  resetMenuRegistry(): void {
    this.sections.clear();
    this.items.clear();
    this.contributions.clear();
    this.ownedNodeIds.clear();
    this.currentContributionId = null;
    this.bump();
  }

  private trackOwned(nodeId: string): void {
    if (!this.currentContributionId) return;
    const owned = this.ownedNodeIds.get(this.currentContributionId);
    owned?.add(nodeId);
  }

  private removeOwned(contributionId: string): void {
    const owned = this.ownedNodeIds.get(contributionId);
    if (!owned) return;
    for (const nodeId of owned) {
      this.sections.delete(nodeId);
      this.items.delete(nodeId);
    }
    owned.clear();
  }

  private assertUniqueId(id: string): void {
    if (this.sections.has(id) || this.items.has(id)) {
      throw new Error(`Узел меню «${id}» уже зарегистрирован`);
    }
  }

  private assertNoCycles(): void {
    const visiting = new Set<string>();
    const visited = new Set<string>();
    const visit = (id: string): void => {
      if (visited.has(id)) return;
      if (visiting.has(id)) {
        throw new Error(`Цикл секций меню у «${id}»`);
      }
      visiting.add(id);
      const parentId = this.sections.get(id)?.parentId;
      if (parentId) visit(parentId);
      visiting.delete(id);
      visited.add(id);
    };
    for (const id of this.sections.keys()) {
      visit(id);
    }
  }

  private toTreeItem(menuItem: MenuItem): MenuTreeItem {
    return {
      kind: 'menuItem',
      id: menuItem.id,
      title: menuItem.title,
      to: menuItem.to,
      icon: menuItem.icon,
      exact: menuItem.exact,
      order: menuItem.order,
    };
  }

  private pruneEmptySections(nodes: MenuTreeNode[]): MenuTreeNode[] {
    const result: MenuTreeNode[] = [];
    for (const node of nodes) {
      if (node.kind === 'menuItem') {
        result.push(node);
        continue;
      }
      node.children = this.pruneEmptySections(node.children);
      if (node.children.length > 0) result.push(node);
    }

    return result;
  }

  private sortTree(node: MenuTreeNode): void {
    if (node.kind !== 'menuSection') return;
    this.sortNodes(node.children);
    for (const child of node.children) {
      this.sortTree(child);
    }
  }

  private sortNodes(nodes: MenuTreeNode[]): void {
    nodes.sort((left, right) => left.order - right.order || left.id.localeCompare(right.id));
  }

  private bump(): void {
    this.revisionCounter += 1;
    for (const listener of this.listeners) {
      listener();
    }
  }
}
