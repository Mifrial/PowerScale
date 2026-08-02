import type { ICSRFApi } from '../Interface/ICSRFApi'

export class CsrfApi implements ICSRFApi {
  private cookieName = 'csrf-token'

  async initToken(): Promise<void> {
    // In real mode the backend sets csrf-token cookie on login.
    // Nothing extra to fetch — just read the cookie when needed.
  }

  getToken(): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${this.cookieName}=([^;]*)`))
    return match ? match[1] : null
  }
}
