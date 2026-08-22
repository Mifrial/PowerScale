import type { ICSRFApi } from '@/modules/Core/Engine/Interface/ICSRFApi';

let token = '';

export const mockCsrfApi: ICSRFApi = {
  async initToken() {
    token = crypto.randomUUID();
  },
  getToken() {
    return token;
  },
};
