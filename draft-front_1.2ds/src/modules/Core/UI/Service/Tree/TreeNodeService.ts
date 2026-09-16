import type { TreeNode } from '@/modules/Core/UI/Dto/Tree/TreeNode';
import type { TreeNodeSource } from '@/modules/Core/UI/Dto/Tree/TreeNodeSource';

/** Собирает вложенное дерево из плоского списка с parentId. */
export class TreeNodeService {
  nest(items: TreeNodeSource[]): TreeNode[] {
    const childrenByParent = new Map<string | null, TreeNodeSource[]>();
    for (const item of items) {
      const siblings = childrenByParent.get(item.parentId) ?? [];
      siblings.push(item);
      childrenByParent.set(item.parentId, siblings);
    }
    for (const siblings of childrenByParent.values()) {
      siblings.sort((left, right) => (left.sortOrder ?? 0) - (right.sortOrder ?? 0) || left.id.localeCompare(right.id));
    }

    const toNode = (item: TreeNodeSource): TreeNode => ({
      id: item.id,
      label: item.label,
      children: (childrenByParent.get(item.id) ?? []).map(toNode),
    });

    return (childrenByParent.get(null) ?? []).map(toNode);
  }

  filter(nodes: TreeNode[], query: string): TreeNode[] {
    const needle = query.trim().toLocaleLowerCase();
    if (!needle) return nodes;

    const visit = (node: TreeNode): TreeNode | null => {
      if (node.label.toLocaleLowerCase().includes(needle)) return node;
      const children = node.children.map(visit).filter((child): child is TreeNode => child !== null);
      if (children.length === 0) return null;

      return { id: node.id, label: node.label, children };
    };

    return nodes.map(visit).filter((node): node is TreeNode => node !== null);
  }

  parentIds(nodes: TreeNode[]): string[] {
    const ids: string[] = [];
    const visit = (node: TreeNode): void => {
      if (node.children.length === 0) return;
      ids.push(node.id);
      node.children.forEach(visit);
    };
    nodes.forEach(visit);

    return ids;
  }

  find(nodes: TreeNode[], id: string): TreeNode | null {
    for (const node of nodes) {
      if (node.id === id) return node;
      const nested = this.find(node.children, id);
      if (nested) return nested;
    }

    return null;
  }

  ancestorIds(nodes: TreeNode[], id: string): string[] {
    const walk = (items: TreeNode[], trail: string[]): string[] | null => {
      for (const node of items) {
        if (node.id === id) return trail;
        const found = walk(node.children, [...trail, node.id]);
        if (found) return found;
      }

      return null;
    };

    return walk(nodes, []) ?? [];
  }
}
