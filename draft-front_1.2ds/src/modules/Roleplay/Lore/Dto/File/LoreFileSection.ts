/** Раздел в файле `powerscale.lore`. */
export interface LoreFileSection {
  code: string;
  name: string;
  description: string;
  parentCode: string | null;
  sortOrder: number;
  icon: string;
}
