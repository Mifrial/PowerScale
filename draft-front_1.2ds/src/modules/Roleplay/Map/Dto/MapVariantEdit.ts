/** Правка варианта в черновике. */
export interface MapVariantEdit {
  label: string;
  fromMinutes: number | null;
  sortOrder: number;
  filePublicId: string | null;
  /** Ширина выбранного файла; размеры приходят из Core/File, не из клиента. */
  fileWidth?: number | null;
  /** Высота выбранного файла. */
  fileHeight?: number | null;
  isBaseFileTouched: boolean;
  millimetersPerPixel: number | null;
  isDefault: boolean;
}
