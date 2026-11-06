/** Вариант в снимке публикации: подложка задаётся внешним id. */
export interface MapSaveVariant {
  id: number;
  label: string;
  fromMinutes: number | null;
  sortOrder: number;
  filePublicId: string | null;
  millimetersPerPixel: number | null;
  isDefault: boolean;
}
