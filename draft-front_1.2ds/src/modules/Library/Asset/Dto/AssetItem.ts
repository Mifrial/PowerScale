/** Запись ассета из JSON-вида Library/Asset. */
export interface AssetItem {
  id: number;
  collectionModule: string;
  collectionCode: string;
  collectionTitle: string;
  filePublicId: string | null;
  /** Имя загруженного файла: стабильный ключ пресетов потребителя. */
  originalName?: string | null;
  name: string;
  width: number | null;
  height: number | null;
  mime: string | null;
  size: number | null;
  isShared: boolean;
  ownerId: number | null;
  isArchived: boolean;
  createdAt: number;
}
