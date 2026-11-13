import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IAssetApi } from '@/modules/Library/Asset/Interface/IAssetApi';

export function registerAssetApi(api: IAssetApi): void {
  serviceLocator.set('Library.Asset.Service.AssetApi', api);
}

export function getAssetApi(): IAssetApi {
  return serviceLocator.get('Library.Asset.Service.AssetApi');
}
