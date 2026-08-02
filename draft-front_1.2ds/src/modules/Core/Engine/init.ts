import { serviceLocator } from './Service/ServiceLocator'
import type { ICSRFApi } from './Interface/ICSRFApi'

export { HttpClient } from './Service/HttpClient'
export type { HttpClientConfig } from './Service/HttpClient'
export { Engine } from './Service/Engine'
export type { ActionResponse, ActionError } from './Dto/ActionResponse'
export { serviceLocator } from './Service/ServiceLocator'

export function registerCsrfApi(api: ICSRFApi): void {
  serviceLocator.set('Core.Engine.Http.CsrfApi', api)
}

export function getCsrfApi(): ICSRFApi {
  return serviceLocator.get('Core.Engine.Http.CsrfApi')
}
