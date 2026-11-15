import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';

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
  /** Данные потребителя: у сцены здесь лежит шаблон объекта. */
  meta?: Record<string, unknown> | null;
  width: number | null;
  height: number | null;
  /** Видимая рамка картинки; null — не считалась. */
  contentBox?: ImageContentBox | null;
  mime: string | null;
  size: number | null;
  isShared: boolean;
  ownerId: number | null;
  isArchived: boolean;
  createdAt: number;
}
