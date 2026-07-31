import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { IAuthApi } from '@/modules/Core/Auth/Interface/IAuthApi'

export function registerAuthApi(api: IAuthApi): void {
  sl.set('Core.Auth.Service.AuthApi', api)
}

export function getAuthApi(): IAuthApi {
  return sl.get('Core.Auth.Service.AuthApi')
}
