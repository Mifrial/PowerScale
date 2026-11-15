/** Узел дерева для `lore.saveTree`. */
export interface LoreSectionInput {
  code: string;
  name: string;
  description: string;
  parentCode: string | null;
  sortOrder: number;
  icon: string;
}
