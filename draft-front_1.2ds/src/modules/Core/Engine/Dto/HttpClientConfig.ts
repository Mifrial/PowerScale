export interface HttpClientConfig {
  baseUrl: string
  onUnauthorized?: () => void
  getCsrfToken?: () => string | null
}
