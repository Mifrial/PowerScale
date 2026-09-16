export interface TreeNodeSource {
  id: string;
  label: string;
  parentId: string | null;
  sortOrder?: number;
}
