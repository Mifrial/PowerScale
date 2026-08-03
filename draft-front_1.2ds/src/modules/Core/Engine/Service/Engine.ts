import type { HttpClient } from '@/modules/Core/Engine/Service/HttpClient';
import type { ActionResponse } from '@/modules/Core/Engine/Dto/ActionResponse';

export class Engine {
  constructor(private readonly http: HttpClient) {}

  getBaseUrl(): string {
    return this.http.getBaseUrl();
  }

  async runAction<T>(action: string, payload?: unknown, signal?: AbortSignal): Promise<ActionResponse<T>> {
    const res = await this.http.post<ActionResponse<T>>(`/run?action=${encodeURIComponent(action)}`, payload, signal);

    return res.data;
  }
}
