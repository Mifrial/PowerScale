import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';

export { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
export type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';
export { Engine } from '@/modules/Core/Engine/Service/Engine';
export type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
export type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/**
 * Регистрирует реализацию CSRF API в локаторе.
 *
 * @param api Порт CSRF.
 */
export function registerCsrfApi(api: ICSRFApi): void {
  serviceLocator.set('Core.Engine.Service.CsrfApi', api);
}

/**
 * Возвращает зарегистрированный CSRF API.
 */
export function getCsrfApi(): ICSRFApi {
  return serviceLocator.get('Core.Engine.Service.CsrfApi');
}

/**
 * Сбрасывает регистрации локатора (тесты и смена режима).
 */
export function resetRegisteredApis(): void {
  serviceLocator.reset();
}
