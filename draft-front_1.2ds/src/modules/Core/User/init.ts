import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { IUserApi } from '@/modules/Core/User/Interface/IUserApi'
import type { IGroupApi } from '@/modules/Core/User/Interface/IGroupApi'

export function registerUserApi(api: IUserApi): void {
  sl.set('Core.User.Service.UserApi', api)
}

export function getUserApi(): IUserApi {
  return sl.get('Core.User.Service.UserApi')
}

export function registerGroupApi(api: IGroupApi): void {
  sl.set('Core.User.Service.GroupApi', api)
}

export function getGroupApi(): IGroupApi {
  return sl.get('Core.User.Service.GroupApi')
}
