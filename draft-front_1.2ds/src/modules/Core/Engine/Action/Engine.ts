import { HttpClient } from '../Http/HttpClient'
import type { ActionResponse } from './ActionResponse'

export class Engine {
  constructor(private http: HttpClient) {}

  getBaseUrl(): string {
    return this.http.getBaseUrl()
  }

  async runAction<T>(action: string, payload?: unknown, signal?: AbortSignal): Promise<ActionResponse<T>> {
    const res = await this.http.post<ActionResponse<T>>(`/run?action=${action}`, payload, signal)
    return res.data
  }
}
