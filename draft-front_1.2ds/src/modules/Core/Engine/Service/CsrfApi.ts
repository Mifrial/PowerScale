import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';

/**
 * Читает CSRF-токен из cookie, выставленной бэкендом.
 */
export class CsrfApi implements ICSRFApi {
  private readonly cookieName = 'csrf-token';

  /**
   * В real-режиме бэк кладёт csrf-token в куки при логине — дополнительно ничего не запрашиваем.
   */
  async initToken(): Promise<void> {}

  /**
   * Возвращает значение cookie csrf-token или null.
   */
  getToken(): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|;\\s*)${this.cookieName}=([^;]*)`));

    return match ? match[1] : null;
  }
}
