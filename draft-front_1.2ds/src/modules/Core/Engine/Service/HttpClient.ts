import type { HttpClientConfig } from '@/modules/Core/Engine/Dto/HttpClientConfig';
import type { HttpResponse } from '@/modules/Core/Engine/Dto/HttpResponse';
import type { SseHandle } from '@/modules/Core/Engine/Dto/SseHandle';
import type { SseHandlers } from '@/modules/Core/Engine/Dto/SseHandlers';

/**
 * Транспорт JSON POST и GET SSE: cookie, AUTH_REQUIRED, без Chat.
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
   * Открывает GET event-stream. Хендл сразу; отказы только onError, не throw.
   *
   * @param path Путь относительно baseUrl.
   * @param query Ключи; undefined/null не слать; 0 слать.
   * @param handlers Кадр и отказ канала.
   */
  openSse(path: string, query: Record<string, string | number | undefined>, handlers: SseHandlers): SseHandle {
    const controller = new AbortController();
    void this.consumeSse(path, query, handlers, controller.signal);

    return {
      close: () => {
        controller.abort();
      },
    };
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
   * Текст ошибки конверта.
   *
   * @param data Тело JSON.
   */
  private errorMessageOf(data: unknown): string | null {
    if (data === null || typeof data !== 'object' || !('error' in data)) {
      return null;
    }

    const error = data.error;
    if (error === null || typeof error !== 'object' || !('message' in error)) {
      return null;
    }

    return typeof error.message === 'string' ? error.message : null;
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

  /**
   * Клеит query: 0 остаётся, undefined/null нет.
   *
   * @param query Карта параметров.
   */
  private encodeQuery(query: Record<string, string | number | undefined>): string {
    const parts: string[] = [];
    for (const [name, value] of Object.entries(query)) {
      if (value === undefined || value === null) continue;
      parts.push(`${encodeURIComponent(name)}=${encodeURIComponent(String(value))}`);
    }

    return parts.length === 0 ? '' : `?${parts.join('&')}`;
  }

  /**
   * Fetch потока: abort — выход; иначе onError.
   *
   * @param path Путь.
   * @param query Параметры.
   * @param handlers Колбэки.
   * @param signal Отмена.
   */
  private async consumeSse(
    path: string,
    query: Record<string, string | number | undefined>,
    handlers: SseHandlers,
    signal: AbortSignal,
  ): Promise<void> {
    try {
      const res = await fetch(`${this.config.baseUrl}${path}${this.encodeQuery(query)}`, {
        method: 'GET',
        credentials: 'include',
        headers: { Accept: 'text/event-stream' },
        signal,
      });
      if (signal.aborted) return;
      if (!res.ok) {
        await this.failSseStatus(res, handlers);

        return;
      }

      await this.readSseBody(res, handlers, signal);
    } catch (caught) {
      if (signal.aborted || this.isAbortError(caught)) return;
      handlers.onError({
        code: 'INTERNAL',
        message: caught instanceof Error ? caught.message : 'Соединение чата прервано',
      });
    }
  }

  /**
   * 400 JSON: AUTH_REQUIRED — login без onError; иначе код канала.
   *
   * @param res HTTP-ответ.
   * @param handlers Колбэки.
   */
  private async failSseStatus(res: Response, handlers: SseHandlers): Promise<void> {
    let data: unknown;
    try {
      data = await this.parseJsonBody(res);
    } catch {
      handlers.onError({ code: 'INTERNAL', message: `HTTP ${res.status}` });

      return;
    }

    if (this.shouldRedirectToLogin(res.status, data)) {
      this.redirectToLogin();

      return;
    }

    handlers.onError({
      code: this.errorCodeOf(data) ?? 'INTERNAL',
      message: this.errorMessageOf(data) ?? `HTTP ${res.status}`,
    });
  }

  /**
   * Читает байты SSE до конца или abort.
   *
   * @param res 2xx с телом потока.
   * @param handlers Колбэки.
   * @param signal Отмена.
   */
  private async readSseBody(res: Response, handlers: SseHandlers, signal: AbortSignal): Promise<void> {
    const reader = res.body?.getReader();
    if (!reader) {
      handlers.onError({ code: 'INTERNAL', message: 'пустое тело потока' });

      return;
    }

    const decoder = new TextDecoder();
    let buffer = '';
    let eventName = '';
    const dataLines: string[] = [];
    try {
      while (!signal.aborted) {
        const chunk = await reader.read();
        if (chunk.done) break;
        buffer += decoder.decode(chunk.value, { stream: true });
        const parsed = this.takeSseLines(buffer, eventName, dataLines, handlers);
        buffer = parsed.buffer;
        eventName = parsed.eventName;
      }
      if (!signal.aborted) {
        handlers.onError({ code: 'INTERNAL', message: 'Соединение чата прервано' });
      }
    } finally {
      reader.releaseLock();
    }
  }

  /**
   * Разбирает полные строки буфера в события.
   *
   * @param buffer Хвост текста.
   * @param eventName Текущее имя event.
   * @param dataLines Строки data.
   * @param handlers Колбэки.
   */
  private takeSseLines(
    buffer: string,
    eventName: string,
    dataLines: string[],
    handlers: SseHandlers,
  ): { buffer: string; eventName: string } {
    const lines = buffer.split('\n');
    const rest = lines.pop() ?? '';
    let currentEvent = eventName;
    for (const rawLine of lines) {
      const line = rawLine.endsWith('\r') ? rawLine.slice(0, -1) : rawLine;
      if (line === '') {
        this.dispatchSseEvent(currentEvent, dataLines, handlers);
        currentEvent = '';
        continue;
      }
      if (line.startsWith(':')) continue;
      if (line.startsWith('event:')) {
        currentEvent = line.slice(6).trim();
        continue;
      }
      if (line.startsWith('data:')) {
        dataLines.push(line.slice(5).trimStart());
      }
    }

    return { buffer: rest, eventName: currentEvent };
  }

  /**
   * JSON data → onEvent; мусор — тишина.
   *
   * @param eventName Имя или message.
   * @param dataLines Строки data.
   * @param handlers Колбэки.
   */
  private dispatchSseEvent(eventName: string, dataLines: string[], handlers: SseHandlers): void {
    const raw = dataLines.join('\n');
    dataLines.length = 0;
    if (raw === '') return;
    try {
      handlers.onEvent(eventName === '' ? 'message' : eventName, JSON.parse(raw) as unknown);
    } catch {
      // Мусорный кадр курсор не двигает и канал не роняет.
    }
  }

  /**
   * Abort fetch / reader.
   *
   * @param caught Исключение.
   */
  private isAbortError(caught: unknown): boolean {
    return caught instanceof DOMException && caught.name === 'AbortError';
  }
}
