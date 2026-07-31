import { sl } from '@/modules/Core/Engine'
import type { ISourceApi } from './Interface/ISourceApi'

export function registerSourceApi(api: ISourceApi): void {
  sl.set('Roleplay.Rule.Source.Service.SourceApi', api)
}

export function getSourceApi(): ISourceApi {
  return sl.get('Roleplay.Rule.Source.Service.SourceApi')
}
