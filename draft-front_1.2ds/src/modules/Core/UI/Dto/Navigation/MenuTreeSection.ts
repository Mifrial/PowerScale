import type { MenuTreeNode } from '@/modules/Core/UI/Dto/Navigation/MenuTreeNode';

export interface MenuTreeSection {
  kind: 'menuSection';
  id: string;
  title: string;
  icon?: string;
  order: number;
  children: MenuTreeNode[];
}
