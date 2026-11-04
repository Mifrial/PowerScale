import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
import type { IFileApi } from '@/modules/Core/File/Interface/IFileApi';
import type { FilePage } from '@/modules/Core/File/Dto/FilePage';
import type { FileQuery } from '@/modules/Core/File/Dto/FileQuery';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';
import { FileApiError } from '@/modules/Core/File/Dto/FileApiError';

/** HTTP-транспорт Core/File: upload, findPage, get и URL байтов. */
export class FileApi implements IFileApi {
  constructor(private readonly engine: Engine) {}

  async upload(file: File, signal?: AbortSignal): Promise<FileRecord> {
    const form = new FormData();
    form.append('file', file);
    const response = await this.engine.postForm<FileRecord>('/file/upload', form, signal);

    return this.requireData(response, 'File upload failed');
  }

  async findPage(query: FileQuery, signal?: AbortSignal): Promise<FilePage> {
    const response = await this.engine.runAction<FilePage>('file.findPage', query, signal);

    return this.requireData(response, 'File page is empty');
  }

  async get(id: number, signal?: AbortSignal): Promise<FileRecord> {
    const response = await this.engine.runAction<FileRecord>('file.get', { id }, signal);

    return this.requireData(response, 'File is not found');
  }

  getViewUrl(publicId: string): string {
    return `${this.engine.getBaseUrl()}/file/${publicId}`;
  }

  private requireData<T>(response: ActionResponse<T>, fallbackMessage: string): T {
    if (response.success && response.data) return response.data;
    const error = response.error;

    throw new FileApiError(error?.code ?? 'INTERNAL', error?.message ?? fallbackMessage, error?.details);
  }
}
