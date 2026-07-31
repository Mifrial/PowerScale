import type { ICSRFApi } from '../Interface/ICSRFApi'

let token = ''

export const mockCsrfApi: ICSRFApi = {
  async initToken() {
    token = crypto.randomUUID()
  },
  getToken() {
    return token
  },
}
