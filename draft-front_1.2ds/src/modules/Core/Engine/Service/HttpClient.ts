import type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';
import type { HttpResponse } from '@/modules/Core/Engine/Dto/HttpResponse';

/**
 * Транспорт JSON POST с CSRF-заголовком и разбором тела ответа.
 */
export class HttpClient {
  /**
   * Сохраняет настройки транспорта.
   *
   * @param config Базовый URL, CSRF и реакция на 401.
   */
  constructor(private readonly config: HttpClientConfig) {}

  /**
   * Возвращает базовый URL API.
   */
  getBaseUrl(): string {
    return this.config.baseUrl;
  }

  /**
   * Отправляет POST и возвращает статус вместе с разобранным JSON.
   *
   * @param path Путь относительно baseUrl.
   * @param body Тело запроса.
   * @param signal Сигнал отмены.
   */
  async post<T>(path: string, body?: unknown, signal?: AbortSignal): Promise<HttpResponse<T>> {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    };

    const csrfToken = this.config.getCsrfToken?.();
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken;
    }

    const res = await fetch(`${this.config.baseUrl}${path}`, {
      method: 'POST',
      credentials: 'include',
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    });

    if (res.status === 401) {
      const onUnauthorized =
        this.config.onUnauthorized ??
        (() => {
          if (!window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
          }
        });
      onUnauthorized();
    }

    const data = await this.parseJsonBody<T>(res);

    return { ok: res.ok, status: res.status, data };
  }

  /**
   * Читает тело ответа как JSON или бросает ошибку разбора.
   *
   * @param res HTTP-ответ fetch.
   */
  private async parseJsonBody<T>(res: Response): Promise<T> {
    const text = await res.text();
    if (!text) {
      throw this.parseError(res, 'пустое тело ответа');
    }

    try {
      return JSON.parse(text) as T;
    } catch {
      throw this.parseError(res, text);
    }
  }

  /**
   * Собирает сообщение об ошибке разбора JSON.
   *
   * @param res HTTP-ответ fetch.
   * @param bodyPreview Фрагмент тела для диагностики.
   */
  private parseError(res: Response, bodyPreview: string): Error {
    const preview = bodyPreview.replace(/\s+/g, ' ').slice(0, 120);

    return new Error(`Не удалось разобрать JSON-ответ (${res.status} ${res.statusText}): ${preview}`);
  }
}
