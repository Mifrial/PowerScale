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
  sha256: string;
  isArchived: boolean;
  archivedAt: number | null;
  createdAt: number;
}
