/**
 * Порт выдачи CSRF-токена для HTTP-клиента.
 */
export interface ICSRFApi {
  initToken(): Promise<void>;
  getToken(): string | null;
}
