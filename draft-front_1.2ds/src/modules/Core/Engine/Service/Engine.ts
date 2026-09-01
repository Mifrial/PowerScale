import type { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';

/**
 * Фасад вызова серверных action через HTTP-клиент.
 */
export class Engine {
  /**
   * Сохраняет транспорт для запросов action.
   *
   * @param http Клиент JSON POST.
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
}
