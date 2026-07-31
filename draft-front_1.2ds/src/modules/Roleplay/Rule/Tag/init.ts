import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { ITagApi } from '@/modules/Roleplay/Rule/Tag/Interface/ITagApi'

export function registerTagApi(api: ITagApi): void {
  sl.set('Roleplay.Rule.Tag.Service.TagApi', api)
}

export function getTagApi(): ITagApi {
  return sl.get('Roleplay.Rule.Tag.Service.TagApi')
}
