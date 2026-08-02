export interface HttpClientConfig {
  baseUrl: string
  onUnauthorized?: () => void
  getCsrfToken?: () => string | null
}

interface HttpResponse<T> {
  ok: boolean
  status: number
  data: T
}

export class HttpClient {
  constructor(private config: HttpClientConfig) {}

  getBaseUrl(): string {
    return this.config.baseUrl
  }

  async post<T>(path: string, body?: unknown, signal?: AbortSignal): Promise<HttpResponse<T>> {
    const headers: Record<string, string> = { 'Content-Type': 'application/json' }

    const csrfToken = this.config.getCsrfToken?.()
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken
    }

    const res = await fetch(`${this.config.baseUrl}${path}`, {
      method: 'POST',
      credentials: 'include',
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal,
    })

    if (res.status === 401) {
      const onUnauthorized = this.config.onUnauthorized ?? (() => {
        if (!window.location.pathname.startsWith('/login')) {
          window.location.href = '/login'
        }
      })
      onUnauthorized()
    }

    const data = await res.json() as T
    return { ok: res.ok, status: res.status, data }
  }
}
