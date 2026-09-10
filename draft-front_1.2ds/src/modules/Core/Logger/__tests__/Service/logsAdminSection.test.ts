import { describe, it, expect, beforeEach } from 'vitest';
import { resetPermissionRegistries } from '@/modules/Core/User/init';
import { applyMenuContributions, getMenuTree, registerMenuSection, resetMenuRegistry } from '@/modules/Core/UI/init';
import { ADMINISTRATION_MENU_SECTION } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION';
import { registerLoggerModule } from '@/modules/Core/Logger/init';
import type { User } from '@/modules/Core/User/Dto/User';
import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';

const user = (permissions: string[], hasBypass = false): User => ({
  id: 1,
  name: 'U',
  login: 'u',
  email: 'u@t',
  groups: [],
  registered: 0,
  active: true,
  permissions,
  bypass: hasBypass,
});

function adminItemIds(tree: MenuTreeNode[]): string[] {
  const admin = tree.find((node) => node.id === 'administration');
  if (!admin || admin.kind !== 'menuSection') return [];

  return admin.children.map((node) => node.id);
}

describe('пункт журнала в админке', () => {
  beforeEach(() => {
    resetPermissionRegistries();
    resetMenuRegistry();
    registerMenuSection(ADMINISTRATION_MENU_SECTION);
    registerLoggerModule();
  });

  it('без logger.view пункт не кладётся', () => {
    applyMenuContributions(user(['user_group.view']));
    expect(adminItemIds(getMenuTree())).toEqual([]);
  });

  it('с logger.view пункт кладётся', () => {
    applyMenuContributions(user(['logger.view']));
    expect(adminItemIds(getMenuTree())).toEqual(['logs']);
  });
});
