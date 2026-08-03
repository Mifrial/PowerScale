import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi'

export class CsrfApi implements ICSRFApi {
  private readonly cookieName = 'csrf-token'

  async initToken(): Promise<void> {
    // В real-режиме бэк кладёт csrf-token в куки при логине — дополнительно ничего не запрашиваем.
  }

  getToken(): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${this.cookieName}=([^;]*)`))
    return match ? match[1] : null
  }
}
