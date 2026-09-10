export interface MenuTreeItem {
  kind: 'menuItem';
  id: string;
  title: string;
  to: string;
  icon?: string;
  exact?: boolean;
  order: number;
}
