export interface MenuSection {
  kind: 'menuSection';
  id: string;
  title: string;
  icon?: string;
  parentId?: string;
  order: number;
}
