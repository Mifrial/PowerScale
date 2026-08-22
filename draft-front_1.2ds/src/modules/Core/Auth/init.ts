import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi';

export { passwordValidatorService } from '@/modules/Core/Auth/Service/Instance/passwordValidatorService';

export function registerAuthApi(api: IAuthApi): void {
  serviceLocator.set('Core.Auth.Service.AuthApi', api);
}

export function getAuthApi(): IAuthApi {
  return serviceLocator.get('Core.Auth.Service.AuthApi');
}
