import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { ISpaceApi } from '@/modules/Roleplay/Space/Interface/ISpaceApi'

export function registerSpaceApi(api: ISpaceApi): void {
  sl.set('Roleplay.Space.Service.SpaceApi', api)
}

export function getSpaceApi(): ISpaceApi {
  return sl.get('Roleplay.Space.Service.SpaceApi')
}
