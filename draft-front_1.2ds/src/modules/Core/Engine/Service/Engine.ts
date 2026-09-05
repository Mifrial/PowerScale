import type { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';
import type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
import type { SseHandlers } from '@/modules/Core/Engine/Dto/SseHandlers';

/**
 * Фасад вызова серверных action и живого GET-потока.
 */
export class Engine {
  /**
   * Сохраняет транспорт для запросов action и SSE.
   *
   * @param http Клиент JSON POST и GET SSE.
   */
  constructor(private readonly http: HttpClient) {}

  /**
   * Возвращает базовый URL API.
   */
  getBaseUrl(): string {
    return this.http.getBaseUrl();
  }

  /**
   * Выполняет именованное действие и возвращает конверт ответа.
   *
   * @param action Код действия.
   * @param payload Тело JSON-запроса.
   * @param signal Сигнал отмены запроса.
   */
  async runAction<T>(action: string, payload?: unknown, signal?: AbortSignal): Promise<ActionResponse<T>> {
    const res = await this.http.post<ActionResponse<T>>(`/run?action=${encodeURIComponent(action)}`, payload, signal);

    return res.data;
  }

  /**
   * Открывает SSE. Хендл сразу; отказы только в handlers.
   *
   * @param path Путь относительно baseUrl.
   * @param query Ключи; 0 слать, undefined нет.
   * @param handlers Кадр и отказ канала.
   */
  openSse(path: string, query: Record<string, string | number | undefined>, handlers: SseHandlers): SseHandle {
    return this.http.openSse(path, query, handlers);
  }
}
