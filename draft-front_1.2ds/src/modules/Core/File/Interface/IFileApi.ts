import type { FilePage } from '@/modules/Core/File/Dto/FilePage';
import type { FileQuery } from '@/modules/Core/File/Dto/FileQuery';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';

export interface IFileApi {
  upload(file: File, signal?: AbortSignal): Promise<FileRecord>;
  findPage(query: FileQuery, signal?: AbortSignal): Promise<FilePage>;
  findOwnImages(limit: number, offset: number, signal?: AbortSignal): Promise<FilePage>;
  get(id: number, signal?: AbortSignal): Promise<FileRecord>;
  getViewUrl(publicId: string): string;
}
