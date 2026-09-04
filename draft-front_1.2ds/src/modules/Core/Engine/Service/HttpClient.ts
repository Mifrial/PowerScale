import type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';
import type { HttpResponse } from '@/modules/Core/Engine/Dto/HttpResponse';

/**
 * Транспорт JSON POST с CSRF-заголовком и разбором тела ответа.
 */
export class HttpClient {
  /**
   * Сохраняет настройки транспорта.
   *
   * @param config Базовый URL, CSRF и реакция на AUTH_REQUIRED.
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

    const data = await this.parseJsonBody<T>(res);
    if (this.shouldRedirectToLogin(res.status, data)) {
      this.redirectToLogin();
    }

    return { ok: res.ok, status: res.status, data };
  }

  /**
   * AUTH_REQUIRED на HTTP 400 — нет сессии. CSRF 403 не вылогинивает.
   *
   * @param status Код HTTP.
   * @param data Тело JSON.
   */
  private shouldRedirectToLogin(status: number, data: unknown): boolean {
    return status === 400 && this.errorCodeOf(data) === 'AUTH_REQUIRED';
  }

  /**
   * Код ошибки конверта action.
   *
   * @param data Тело JSON.
   */
  private errorCodeOf(data: unknown): string | null {
    if (data === null || typeof data !== 'object' || !('error' in data)) {
      return null;
    }

    const error = data.error;
    if (error === null || typeof error !== 'object' || !('code' in error)) {
      return null;
    }

    return typeof error.code === 'string' ? error.code : null;
  }

  /**
   * Уводит на login, если ещё не там.
   */
  private redirectToLogin(): void {
    const onUnauthorized =
      this.config.onUnauthorized ??
      (() => {
        if (!window.location.pathname.startsWith('/login')) {
          window.location.href = '/login';
        }
      });
    onUnauthorized();
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
