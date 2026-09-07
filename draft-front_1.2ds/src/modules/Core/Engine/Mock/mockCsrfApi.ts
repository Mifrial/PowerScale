import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';
import { createRandomId } from '@/modules/Core/Engine/Utils/createRandomId';

let token = '';

/** In-memory CSRF API для mock-режима. */
export const mockCsrfApi: ICSRFApi = {
  async initToken() {
    token = createRandomId();
  },
  getToken() {
    return token;
  },
};
