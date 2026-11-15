/** Раздел дерева кодекса. */
export interface LoreSection {
  id: number;
  code: string;
  name: string;
  description: string;
  parentCode: string | null;
  sortOrder: number;
  icon: string;
}
