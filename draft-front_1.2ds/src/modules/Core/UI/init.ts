import type { MenuContribution } from '@/modules/Core/UI/Interface/Navigation/MenuContribution';
import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import type { MenuSection } from '@/modules/Core/UI/Dto/Navigation/MenuSection';
import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';
import { menuRegistry } from '@/modules/Core/UI/Service/Instance/menuRegistry';

export function registerMenuSection(menuSection: MenuSection): void {
  menuRegistry.registerMenuSection(menuSection);
}

export function registerMenuItem(menuItem: MenuItem): void {
  menuRegistry.registerMenuItem(menuItem);
}

export function registerMenuContribution(contribution: MenuContribution): void {
  menuRegistry.registerMenuContribution(contribution);
}

export function applyMenuContributions(actor: unknown): void {
  menuRegistry.applyMenuContributions(actor);
}

export function getMenuTree(): MenuTreeNode[] {
  return menuRegistry.getMenuTree();
}

export function resetMenuRegistry(): void {
  menuRegistry.resetMenuRegistry();
}

export function assertMenuTree(): void {
  menuRegistry.assertMenuTree();
}
