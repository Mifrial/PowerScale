import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';

/** Страница файлов. */
export interface FilePage {
  items: FileRecord[];
  total: number;
}
