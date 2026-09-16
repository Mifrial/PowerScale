import { describe, expect, it } from 'vitest';
import { treeNodeService } from '@/modules/Core/UI/Service/Instance/treeNodeService';

describe('TreeNodeService', () => {
  it('собирает вложенное дерево и ищет узел', () => {
    const tree = treeNodeService.nest([
      { id: 'root', label: 'Корень', parentId: null, sortOrder: 1 },
      { id: 'child', label: 'Потомок', parentId: 'root', sortOrder: 1 },
      { id: 'leaf', label: 'Лист', parentId: 'child', sortOrder: 1 },
    ]);

    expect(tree).toEqual([
      {
        id: 'root',
        label: 'Корень',
        children: [
          {
            id: 'child',
            label: 'Потомок',
            children: [{ id: 'leaf', label: 'Лист', children: [] }],
          },
        ],
      },
    ]);
    expect(treeNodeService.find(tree, 'leaf')?.label).toBe('Лист');
  });

  it('фильтрует по названию, оставляя предков', () => {
    const tree = treeNodeService.nest([
      { id: 'mental', label: 'Ментальные', parentId: null, sortOrder: 1 },
      { id: 'perception', label: 'Восприятие', parentId: 'mental', sortOrder: 1 },
      { id: 'body', label: 'Тело', parentId: null, sortOrder: 2 },
    ]);
    const filtered = treeNodeService.filter(tree, 'восприят');

    expect(filtered.map((node) => node.id)).toEqual(['mental']);
    expect(filtered[0]?.children.map((node) => node.id)).toEqual(['perception']);
    expect(treeNodeService.parentIds(filtered)).toEqual(['mental']);
  });

  it('возвращает путь предков до выбранного узла', () => {
    const tree = treeNodeService.nest([
      { id: 'root', label: 'Корень', parentId: null, sortOrder: 1 },
      { id: 'child', label: 'Потомок', parentId: 'root', sortOrder: 1 },
      { id: 'leaf', label: 'Лист', parentId: 'child', sortOrder: 1 },
    ]);

    expect(treeNodeService.ancestorIds(tree, 'leaf')).toEqual(['root', 'child']);
    expect(treeNodeService.ancestorIds(tree, 'root')).toEqual([]);
  });
});
