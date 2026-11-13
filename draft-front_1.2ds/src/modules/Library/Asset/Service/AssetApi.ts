import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { IAssetApi } from '@/modules/Library/Asset/Interface/IAssetApi';
import type { AssetCreateRequest } from '@/modules/Library/Asset/Dto/AssetCreateRequest';
import type { AssetItem } from '@/modules/Library/Asset/Dto/AssetItem';
import type { AssetPage } from '@/modules/Library/Asset/Dto/AssetPage';
import type { AssetQuery } from '@/modules/Library/Asset/Dto/AssetQuery';
import { AssetApiError } from '@/modules/Library/Asset/Dto/AssetApiError';

/** HTTP-транспорт Library/Asset: страница, создание, переименование и архив. */
export class AssetApi implements IAssetApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: AssetQuery, signal?: AbortSignal): Promise<AssetPage> {
    const response = await this.engine.runAction<AssetPage>('asset.findPage', query, signal);

    return this.requireData(response, 'Asset page is empty');
  }

  async create(request: AssetCreateRequest, signal?: AbortSignal): Promise<AssetItem> {
    const response = await this.engine.runAction<AssetItem>('asset.create', request, signal);

    return this.requireData(response, 'Asset create returned no data');
  }

  async update(id: number, name: string, signal?: AbortSignal): Promise<AssetItem> {
    const response = await this.engine.runAction<AssetItem>('asset.update', { id, name }, signal);

    return this.requireData(response, 'Asset update returned no data');
  }

  async archive(id: number, signal?: AbortSignal): Promise<void> {
    const response = await this.engine.runAction<unknown>('asset.archive', { id }, signal);
    if (!response.success) {
      throw this.errorFrom(response, 'Asset archive failed');
    }
  }

  private requireData<T>(response: ActionResponse<T>, fallbackMessage: string): T {
    if (response.success && response.data) return response.data;

    throw this.errorFrom(response, fallbackMessage);
  }

  private errorFrom(response: ActionResponse<unknown>, fallbackMessage: string): AssetApiError {
    const error = response.error;

    return new AssetApiError(error?.code ?? 'INTERNAL', error?.message ?? fallbackMessage, error?.details);
  }
}
