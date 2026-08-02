import { HttpClient } from './HttpClient'
import type { ActionResponse } from '../Dto/ActionResponse'

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
