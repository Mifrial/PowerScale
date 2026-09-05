import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';
import type { Engine } from '@/modules/Core/Engine/Service/Engine';

export { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
export type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';
export { Engine } from '@/modules/Core/Engine/Service/Engine';
export type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
export type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';
export type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
export type { SseHandlers } from '@/modules/Core/Engine/Dto/SseHandlers';

const ENGINE_PORT = 'Core.Engine.Service.Engine';

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
 * Регистрирует Engine (real). Mock не вызывает.
 *
 * @param engine Фасад HTTP.
 */
export function registerEngine(engine: Engine): void {
  serviceLocator.set(ENGINE_PORT, engine);
}

/**
 * Возвращает Engine, если зарегистрирован.
 */
export function getEngine(): Engine {
  return serviceLocator.get(ENGINE_PORT);
}

/**
 * Engine или null (mock / тесты без real).
 */
export function tryGetEngine(): Engine | null {
  if (!serviceLocator.has(ENGINE_PORT)) return null;

  return serviceLocator.get(ENGINE_PORT);
}

/**
 * Сбрасывает регистрации локатора (тесты и смена режима).
 */
export function resetRegisteredApis(): void {
  serviceLocator.reset();
}
