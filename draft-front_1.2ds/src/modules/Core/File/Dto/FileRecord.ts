import type { ImageContentBox } from '@/modules/Core/File/Dto/ImageContentBox';

/** Запись файла из JSON-вида Core/File. */
export interface FileRecord {
  id: number;
  publicId: string;
  ownerId: number;
  originalName: string;
  mime: string;
  size: number;
  width: number | null;
  height: number | null;
  /** Видимая рамка картинки; null — не считалась или полей нет. */
  contentBox: ImageContentBox | null;
  sha256: string;
  isArchived: boolean;
  isDerived: boolean;
  archivedAt: number | null;
  createdAt: number;
}
