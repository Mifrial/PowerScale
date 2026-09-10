import { describe, it, expect, beforeEach } from 'vitest';
import { MenuRegistry } from '@/modules/Core/UI/Service/MenuRegistry';
import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import type { MenuSection } from '@/modules/Core/UI/Dto/Navigation/MenuSection';

const section = (partial: Partial<MenuSection> & Pick<MenuSection, 'id'>): MenuSection => ({
  kind: 'menuSection',
  title: partial.title ?? partial.id,
  order: partial.order ?? 10,
  parentId: partial.parentId,
  icon: partial.icon,
  id: partial.id,
});

const item = (partial: Partial<MenuItem> & Pick<MenuItem, 'id'>): MenuItem => ({
  kind: 'menuItem',
  title: partial.title ?? partial.id,
  to: partial.to ?? `/${partial.id}`,
  order: partial.order ?? 10,
  sectionId: partial.sectionId,
  icon: partial.icon,
  exact: partial.exact,
  id: partial.id,
});

describe('MenuRegistry', () => {
  let registry: MenuRegistry;

  beforeEach(() => {
    registry = new MenuRegistry();
  });

  it('кладёт корневой пункт', () => {
    registry.registerMenuItem(item({ id: 'home', order: 1 }));
    expect(registry.getMenuTree()).toEqual([expect.objectContaining({ kind: 'menuItem', id: 'home' })]);
  });

  it('вкладывает пункт в секцию и сортирует', () => {
    registry.registerMenuSection(section({ id: 'admin', order: 20 }));
    registry.registerMenuItem(item({ id: 'home', order: 10 }));
    registry.registerMenuItem(item({ id: 'logs', sectionId: 'admin', order: 20 }));
    registry.registerMenuItem(item({ id: 'groups', sectionId: 'admin', order: 10 }));
    const tree = registry.getMenuTree();
    expect(tree.map((node) => node.id)).toEqual(['home', 'admin']);
    const admin = tree[1];
    if (admin.kind !== 'menuSection') throw new Error('expected section');
    expect(admin.children.map((node) => node.id)).toEqual(['groups', 'logs']);
  });

  it('не отдаёт пустую секцию', () => {
    registry.registerMenuSection(section({ id: 'admin' }));
    expect(registry.getMenuTree()).toEqual([]);
  });

  it('вкладывает секции', () => {
    registry.registerMenuSection(section({ id: 'roleplay', order: 1 }));
    registry.registerMenuSection(section({ id: 'rules', parentId: 'roleplay', order: 1 }));
    registry.registerMenuItem(item({ id: 'spaces', sectionId: 'rules' }));
    const tree = registry.getMenuTree();
    expect(tree).toHaveLength(1);
    const roleplay = tree[0];
    if (roleplay.kind !== 'menuSection') throw new Error('expected section');
    expect(roleplay.children[0]?.id).toBe('rules');
  });

  it('дубль id — ошибка', () => {
    registry.registerMenuItem(item({ id: 'home' }));
    expect(() => registry.registerMenuItem(item({ id: 'home' }))).toThrow(/home/);
  });

  it('нет родителя — ошибка', () => {
    registry.registerMenuItem(item({ id: 'logs', sectionId: 'admin' }));
    expect(() => registry.getMenuTree()).toThrow(/admin/);
  });

  it('цикл секций — ошибка', () => {
    registry.registerMenuSection(section({ id: 'a', parentId: 'b' }));
    registry.registerMenuSection(section({ id: 'b', parentId: 'a' }));
    expect(() => registry.getMenuTree()).toThrow(/Цикл/);
  });

  it('вклад снимает свои узлы и не трогает стартовые', () => {
    registry.registerMenuSection(section({ id: 'admin', order: 20 }));
    registry.registerMenuItem(item({ id: 'home', order: 10 }));
    registry.registerMenuContribution({
      id: 'logs',
      apply: (actor) => {
        if (actor) registry.registerMenuItem(item({ id: 'logs', sectionId: 'admin' }));
      },
    });
    registry.applyMenuContributions({ ok: true });
    expect(registry.getMenuTree().some((node) => node.id === 'admin')).toBe(true);
    registry.applyMenuContributions(null);
    const tree = registry.getMenuTree();
    expect(tree.map((node) => node.id)).toEqual(['home']);
    registry.applyMenuContributions({ ok: true });
    expect(registry.getMenuTree().some((node) => node.id === 'admin')).toBe(true);
  });

  it('инкрементирует revision и зовёт подписчика', () => {
    let ticks = 0;
    registry.subscribe(() => {
      ticks += 1;
    });
    registry.registerMenuItem(item({ id: 'home' }));
    expect(registry.revision).toBeGreaterThan(0);
    expect(ticks).toBeGreaterThan(0);
  });
});
