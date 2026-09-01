import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';

let token = '';

/** In-memory CSRF API для mock-режима. */
export const mockCsrfApi: ICSRFApi = {
  async initToken() {
    token = crypto.randomUUID();
  },
  getToken() {
    return token;
  },
};
