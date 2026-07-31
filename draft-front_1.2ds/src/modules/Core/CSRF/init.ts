import { sl } from '@/modules/Core/Engine/ServiceLocator'
import type { ICSRFApi } from '@/modules/Core/CSRF/Interface/ICSRFApi'

export function registerCsrfApi(api: ICSRFApi): void {
  sl.set('Core.CSRF.Service.CsrfApi', api)
}

export function getCsrfApi(): ICSRFApi {
  return sl.get('Core.CSRF.Service.CsrfApi')
}
